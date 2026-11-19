"""支払一覧表 Excel → JSON（クレジット取り込み用）
使い方: python -I parse_credit.py <extractディレクトリ> <出力json> [修正指定.csv]

修正指定.csv（取り込み元,項目,値,理由）のうち、項目が sheet の行だけをここで使う
（そのファイルは1枚目ではなく、値に書いたシートを読む）。ほかの項目は credit:import --fixes で使う。
"""
import sys, os, re, json, datetime, unicodedata
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from xlread import read_book

import csv
ROOT, OUT = sys.argv[1], sys.argv[2]
SHEET_FIXES = {}
if len(sys.argv) > 3:
    with open(sys.argv[3], encoding='utf-8-sig') as f:
        for row in csv.DictReader(f):
            if row['項目'] == 'sheet':
                SHEET_FIXES[row['取り込み元']] = row['値']
EXCLUDE_DIRS = ('顧客クレジット(Enn)',)

def nfkc(s):
    return unicodedata.normalize('NFKC', str(s)).strip()

def col_letter(i):
    s = ''
    i += 1
    while i:
        i, r = divmod(i - 1, 26)
        s = chr(65 + r) + s
    return s

def cell(rows, r, c):
    if r < 0 or r >= len(rows) or c < 0 or c >= len(rows[r]):
        return None
    v = rows[r][c]
    if isinstance(v, str):
        v = v.strip()
        if v == '':
            return None
    return v

def to_int(v):
    if isinstance(v, bool) or v is None:
        return None
    if isinstance(v, (int, float)):
        return int(round(v))
    s = nfkc(v).replace(',', '').replace('¥', '').replace('円', '')
    return int(s) if re.fullmatch(r'-?\d+', s) else None

def excel_serial_to_date(n):
    return (datetime.date(1899, 12, 30) + datetime.timedelta(days=int(n)))

ERA = {'R': 2018, '令和': 2018, 'H': 1988, '平成': 1988}

def parse_year_label(v):
    """('w', 2026) 西暦2桁 / ('era', 2026) 和暦 / None"""
    if v is None:
        return None
    s = nfkc(v)
    m = re.fullmatch(r'(R|H|令和|平成)\s*(\d{1,2})年?', s)
    if m:
        return ('era', ERA[m.group(1)] + int(m.group(2)))
    m = re.fullmatch(r'(\d{1,4})年', s)
    if m:
        n = int(m.group(1))
        return ('num', n)
    return None

def resolve_contract_date(v, first_month):
    """戻り値: (YYYY-MM-DD or None, 原文 or None, 警告 or None)"""
    if v is None:
        return None, None, None
    if isinstance(v, datetime.datetime):
        return v.date().isoformat(), None, None
    if isinstance(v, datetime.date):
        return v.isoformat(), None, None
    if isinstance(v, (int, float)) and 20000 < v < 60000:
        return excel_serial_to_date(v).isoformat(), None, None
    s = nfkc(v)
    m = re.fullmatch(r'(R|H|令和|平成)?\s*(\d{1,4})[./年-](\d{1,2})[./月-](\d{1,2})日?', s)
    if not m:
        return None, s, f'契約日を読めない: {s}'
    era, y, mo, d = m.group(1), int(m.group(2)), int(m.group(3)), int(m.group(4))
    cands = []
    if era:
        cands = [ERA[era] + y]
    elif y >= 1900:
        cands = [y]
    else:
        cands = [1988 + y, 2018 + y]  # 平成・令和
        if y >= 10 and y <= 40:
            cands.append(2000 + y)    # 西暦2桁の可能性
    valid = []
    for yy in cands:
        try:
            valid.append(datetime.date(yy, mo, d))
        except ValueError:
            pass
    if not valid:
        return None, s, f'契約日を読めない: {s}'
    # 契約日は未来になりえない（「27.7.20」を 2027年と読まない。平成27年＝2015年）
    past = [x for x in valid if x <= datetime.date.today()]
    if not past:
        return None, s, f'契約日が未来の日付になる: {s}'
    valid = past
    if first_month:
        fm = datetime.date(int(first_month[:4]), int(first_month[5:7]), 1)
        # 初回支払の 36か月前〜3か月後に入るものを優先し、一番近いもの
        def score(dt):
            diff = (fm.year - dt.year) * 12 + (fm.month - dt.month)
            return (0 if -3 <= diff <= 36 else 1, abs(diff))
        valid.sort(key=score)
        best = valid[0]
        diff = (fm.year - best.year) * 12 + (fm.month - best.month)
        warn = None if -3 <= diff <= 36 else f'契約日と初回支払が離れている: {s}→{best} / 初回 {first_month}'
        # 和暦の読み方が2通り以上あり得て、どちらも範囲内なら警告
        inrange = [x for x in valid if score(x)[0] == 0]
        if len(inrange) > 1:
            warn = f'契約日の年があいまい: {s}'
        return best.isoformat(), s, warn
    if len(valid) == 1:
        return valid[0].isoformat(), s, None
    return None, s, f'契約日の年が決められない（支払予定なし）: {s}'

def find_name_staff(fname):
    """「氏名(担当).xlsx」→ (氏名, 担当, 補足)。担当は最後のかっこ。その他のかっこ書き・後ろの文字は補足"""
    b = nfkc(os.path.splitext(fname)[0])
    groups = re.findall(r'\(([^()]*)\)?', b)
    first = re.split(r'\(', b, maxsplit=1)[0]
    name = re.sub(r'様$', '', first.strip()).strip()
    staff = groups[-1].strip() if groups else None
    extra = [g.strip() for g in groups[:-1]]
    tail = re.sub(r'^.*\)', '', b).strip() if ')' in b else ''
    if tail:
        extra.append(tail)
    return name, staff or None, extra

def parse_sheet(rows):
    warnings = []
    def is_month(i):
        mo = to_int(cell(rows, i, 1))
        return mo is not None and 1 <= mo <= 12
    # 最初の月の行（A列に年、B列に月）
    r_first = next((i for i in range(len(rows)) if is_month(i) and parse_year_label(cell(rows, i, 0))), None)
    if r_first is None:
        return None, ['年月の行が見つからない']
    # 元金・手数料の行（見出しがなければ月の行の直前2行）
    r_pr = next((i for i in range(r_first) if nfkc(cell(rows, i, 0) or '') == '元金'), None)
    if r_pr is None:
        r_pr = r_first - 2
        warnings.append('元金・手数料の見出しなし（位置から判断）')
    r_fee = r_pr + 1
    r_date, r_item = r_pr - 2, r_pr - 1
    # 合計の行：月の行が終わった後、「合計」の見出しか金額のある最初の行
    r_tot = None
    for i in range(r_first, len(rows)):
        if is_month(i):
            continue
        lab = nfkc(cell(rows, i, 0) or '') + nfkc(cell(rows, i, 1) or '')
        if '合計' in lab or any(isinstance(cell(rows, i, c), (int, float)) for c in range(2, len(rows[i]))):
            r_tot = i
            break
    if r_tot is None:
        return None, warnings + ['合計の行が見つからない']
    # 合計の列（ヘッダーの「合計」）
    width = max(len(r) for r in rows[: r_tot + 4])
    c_tot = None
    for i in range(0, r_fee + 1):
        for c in range(2, width):
            if nfkc(cell(rows, i, c) or '') == '合計':
                c_tot = c
    if c_tot is None:
        warnings.append('合計の列が見つからない（最後の列まで契約として読む）')
        c_tot = width
    # 月の行：年月を割り出す。月が戻ったら年を1つ進め（相対の年）、
    # 1月の行の西暦2桁（例 26年）を基準にして実際の年にする
    rel, off, prev_m, anchors = {}, 0, None, []
    for i in range(r_first, r_tot):
        mo = to_int(cell(rows, i, 1))
        if mo is None or not 1 <= mo <= 12:
            continue
        if prev_m is not None and mo <= prev_m:
            off += 1
        prev_m = mo
        rel[i] = (off, mo)
        lab = parse_year_label(cell(rows, i, 0))
        if mo == 1 and lab and lab[0] == 'num':
            n = lab[1]
            anchors.append((n if n >= 1900 else 2000 + n) - off)
    if anchors:
        base = anchors[0]
        if len(set(anchors)) > 1:
            warnings.append('1月の年表記が途中で食い違う（最初の表記に合わせた）')
    else:
        lab = parse_year_label(cell(rows, r_first, 0))
        nxt = parse_year_label(cell(rows, r_first + 1, 0))
        if lab[0] == 'era':
            base = lab[1]
        elif nxt:
            base = lab[1] if lab[1] >= 1900 else 2000 + lab[1]   # 1行目=西暦、2行目=和暦
        else:
            base = 2018 + lab[1] if lab[1] <= 10 else 1988 + lab[1]  # 和暦だけ（推定）
            warnings.append(f'年の表記が和暦だけ。{base}年と推定')
    months = {i: f'{base + o:04d}-{mo:02d}' for i, (o, mo) in rel.items()}
    if not months:
        return None, warnings + ['年月が読めない']
    if len(set(months.values())) != len(months):
        warnings.append('同じ年月の行が重複している')

    contracts = []
    for c in range(2, c_tot):
        date_raw = cell(rows, r_date, c)
        item = cell(rows, r_item, c)
        principal = cell(rows, r_pr, c)
        fee = cell(rows, r_fee, c) if r_fee < r_first else None
        pays, memos = {}, []
        for i in range(r_first, r_tot):
            v = cell(rows, i, c)
            if v is None:
                continue
            n = to_int(v)
            if n is None:
                memos.append(f'{months.get(i, str(i+1)+"行")}：{nfkc(v)}')
            elif n != 0:
                if i not in months:
                    warnings.append(f'{col_letter(c)}列 {i+1}行の金額の年月が不明')
                    continue
                m = months[i]
                pays[m] = pays.get(m, 0) + n
        # 合計の下：信販会社とメモ
        company, below = None, []
        for i in range(r_tot + 1, min(len(rows), r_tot + 6)):
            v = cell(rows, i, c)
            if v is None:
                continue
            below.append(nfkc(v))
            if company is None and isinstance(v, str):
                company = nfkc(v)
        p_int, f_int = to_int(principal), to_int(fee)
        if principal is not None and p_int is None:
            memos.append(f'元金欄：{nfkc(principal)}')
        if fee is not None and f_int is None:
            memos.append(f'手数料欄：{nfkc(fee)}')
        if not pays and not p_int and not date_raw and not item:
            if company or memos or below:
                warnings.append(f'{col_letter(c)}列：金額なしで文字だけ（{company or ""} {" ".join(memos + below)}）')
            continue
        first = min(pays) if pays else None
        cdate, craw, cwarn = resolve_contract_date(date_raw, first)
        if cwarn:
            warnings.append(f'{col_letter(c)}列：{cwarn}')
        if not pays:
            warnings.append(f'{col_letter(c)}列：支払予定の金額がない')
        total = cell(rows, r_tot, c)
        t_int = to_int(total)
        got = sum(pays.values())
        pf = (p_int or 0) + (f_int or 0)
        if t_int is not None and t_int != got and got != pf:
            warnings.append(f'{col_letter(c)}列：合計が合わない（Excel 合計 {t_int} / 元金+手数料 {pf} / 読込 {got}）')
        contracts.append({
            'col': col_letter(c),
            'company_label': company,
            'contract_date': cdate,
            'contract_date_raw': craw,
            'item_name': nfkc(item) if item is not None else None,
            'principal': p_int,
            'fee': f_int,
            'payments': [{'month': m, 'amount': pays[m]} for m in sorted(pays)],
            'below': below,
            'memo': memos,
        })
    # 合計列より右に何か書いてあれば警告
    for i in range(0, r_tot + 4):
        for c in range(c_tot + 1, len(rows[i]) if i < len(rows) else 0):
            v = cell(rows, i, c)
            if v is not None:
                warnings.append(f'合計列より右に記載あり（{col_letter(c)}{i+1}：{nfkc(v)[:20]}）')
                break
    return contracts, warnings

result = []
for dirpath, dirnames, filenames in os.walk(ROOT):
    rel_dir = os.path.relpath(dirpath, ROOT)
    if any(part.startswith(EXCLUDE_DIRS) for part in rel_dir.split(os.sep)):
        continue
    for fn in sorted(filenames):
        rel = os.path.join(rel_dir, fn)
        ext = os.path.splitext(fn)[1].lower()
        rec = {'source_file': rel, 'kind': None, 'warnings': []}
        if ext not in ('.xlsx', '.xls'):
            rec['kind'] = 'skip'
            rec['warnings'].append(f'Excel以外（{ext}）')
            result.append(rec)
            continue
        if '競合コピー' in fn:
            rec['kind'] = 'conflict_copy'
            rec['warnings'].append('Dropbox の競合コピー（元のファイルを使う）')
            result.append(rec)
            continue
        if '原紙' in fn:
            rec['kind'] = 'blank'
            result.append(rec)
            continue
        name, staff, extra = find_name_staff(fn)
        rec.update({'name_from_file': name, 'staff': staff, 'name_note': extra})
        if staff and 'enn' in staff.lower():
            rec['warnings'].append(f'担当欄に Enn（{staff}）')
        try:
            book = read_book(os.path.join(dirpath, fn))
        except Exception as e:
            rec['kind'] = 'error'
            rec['warnings'].append(f'読めない: {e}')
            result.append(rec)
            continue
        nonempty = [(n, r) for n, r in book if any(v not in (None, '') for row in r for v in row)]
        if not nonempty:
            rec['kind'] = 'blank'
            result.append(rec)
            continue
        sheet_name, rows = nonempty[0]
        if rel in SHEET_FIXES:
            hit = [(n, r) for n, r in nonempty if n == SHEET_FIXES[rel]]
            if not hit:
                raise SystemExit(f'修正指定のシートが見つからない: {rel} / {SHEET_FIXES[rel]}')
            sheet_name, rows = hit[0]
            rec['warnings'].append(f'修正指定によりシート「{sheet_name}」を読む')
        elif len(nonempty) > 1:
            rec['warnings'].append('シートが複数（1枚目「%s」だけ読む。他：%s）' % (sheet_name, '、'.join(n for n, _ in nonempty[1:])))
        b1 = cell(rows, 0, 1)
        cname = None
        if b1:
            cname = re.sub(r'^お客様名[:：]\s*', '', nfkc(b1))
            cname = re.sub(r'\s*様$', '', cname).strip()
        rec['customer_name'] = name
        rec['sheet_name'] = cname
        def key(s): return re.sub(r'\s+', '', s or '')
        if cname and key(cname) != key(name):
            rec['warnings'].append(f'ファイル名とシートの名前が違う（{name}／{cname}）')
        contracts, warns = parse_sheet(rows)
        rec['warnings'] += warns
        if contracts is None:
            rec['kind'] = 'error'
        else:
            rec['kind'] = 'customer'
            rec['contracts'] = contracts
            if not contracts:
                rec['kind'] = 'blank'
        result.append(rec)

json.dump(result, open(OUT, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
from collections import Counter
k = Counter(r['kind'] for r in result)
print('files', len(result), dict(k))
cs = [c for r in result if r['kind'] == 'customer' for c in r['contracts']]
print('contracts', len(cs), 'payments', sum(len(c['payments']) for c in cs), 'amount', sum(p['amount'] for c in cs for p in c['payments']))
print('files with warnings', sum(1 for r in result if r['warnings']))
print('company labels', Counter(c['company_label'] for c in cs).most_common(40))
