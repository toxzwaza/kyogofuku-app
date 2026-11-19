"""朝日さんの回答 Excel → 修正指定 CSV（取り込み元,項目,値,理由）"""
import sys, re, csv, json, os, openpyxl
ANS, PARSED, OUT = sys.argv[1:4]
SHEETS = {}  # ファイル名 → 読むシート（回答「シート(2)が正しい」）。引数5以降で 'ファイル名=シート名'
for a in sys.argv[4:]:
    k, v = a.split('=', 1); SHEETS[k] = v
parsed = json.load(open(PARSED, encoding='utf-8'))
by_base = {}
for f in parsed:
    by_base.setdefault(os.path.basename(f['source_file']), []).append(f['source_file'])
ERA = {'令和': 2018, '平成': 1988, '昭和': 1925}
COMPANY = {'「CBS」という信販会社です': 'CBS', '[ABCｷｬﾋﾟﾀﾙ」という信販会社です': 'ABCキャピタル'}
def ymd(s):
    m = re.search(r'(\d{4})年\(?[RHS]?\d*\)?(\d{1,2})月(\d{1,2})日', s)
    return '%04d-%02d-%02d' % tuple(map(int, m.groups())) if m else None
rows, left, seen = [], [], set()
wb = openpyxl.load_workbook(ANS, data_only=True)
for ws in wb:
    if ws.title == 'はじめに':
        continue
    for r in ws.iter_rows(min_row=2, values_only=True):
        if r[0] is None:
            continue
        no, fname, col, q = r[0], r[3], str(r[4]).rstrip('列'), r[13] or ''
        a_co, a_date, a_rest, memo = r[14], r[15], r[16], r[17]
        cand = by_base.get(fname, [])
        if len(cand) != 1:
            left.append((ws.title, no, fname, '取り込み元が特定できない %d件' % len(cand))); continue
        src = cand[0]; key = src + '#' + col
        why = '%s No.%s 朝日さん回答' % (ws.title, no)
        def add(item, val):
            k = (src if item == 'sheet' else key, item)
            if k not in seen:
                seen.add(k); rows.append([key if item != 'sheet' else src, item, val, why])
        if a_co:
            add('company', COMPANY.get(a_co, a_co))
        if a_date == 'OK':
            m = re.search(r'(令和|平成|昭和)(\d+|元)年(\d+)月(\d+)日でよいですか', q)
            if m: add('contract_date', '%04d-%02d-%02d' % (ERA[m[1]] + (1 if m[2] == '元' else int(m[2])), int(m[3]), int(m[4])))
            else: left.append((ws.title, no, fname, '契約日OKだが推定日付が読めない'))
        elif a_date:
            d = ymd(a_date)
            if d: add('contract_date', d)
            else: left.append((ws.title, no, fname, '契約日の回答が読めない: ' + a_date))
        if a_rest in ('組替えしました',):
            add('status', 'cancelled')
        elif a_rest == '支払い終わってます':
            add('status', 'completed')
        elif a_rest in ('合計金額欄です', 'なんの契約でもないです'):
            add('exclude', '1')
        elif a_rest and a_rest.startswith(('シート(2)', 'シート2')):
            if fname in SHEETS: add('sheet', SHEETS[fname])
            else: left.append((ws.title, no, fname, 'シート指定なし'))
        elif a_rest and re.match(r'\d{4}年\(\w+\)\d+月～が正しい', a_rest):
            m = re.match(r'(\d{4})年\(\w+\)(\d+)月', a_rest); add('first_month', '%s-%02d' % (m[1], int(m[2])))
        elif a_rest in (None, '合ってます'):
            pass
        else:
            left.append((ws.title, no, fname + ' ' + col, '保留: ' + a_rest))
        if memo:
            left.append((ws.title, no, fname + ' ' + col, '保留(メモ): ' + memo))
with open(OUT, 'w', encoding='utf-8-sig', newline='') as f:
    w = csv.writer(f); w.writerow(['取り込み元', '項目', '値', '理由']); w.writerows(rows)
import collections
print('修正指定', len(rows), dict(collections.Counter(r[1] for r in rows)))
for x in left: print('残り', *x)
