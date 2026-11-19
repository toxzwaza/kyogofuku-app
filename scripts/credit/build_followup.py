"""追加確認リスト（朝日さん向け）を作る"""
import sys, csv, os, json, openpyxl
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.worksheet.datavalidation import DataValidation
ANS, REPORT, DATES, OUT = sys.argv[1:5]
rep = {r['取り込み元']: r for r in csv.DictReader(open(REPORT, encoding='utf-8-sig'))}
dates = json.load(open(DATES, encoding='utf-8'))
by_base = {}
for k in rep:
    f, col = k.rsplit('#', 1); by_base[(os.path.basename(f), col)] = k
wb = openpyxl.load_workbook(ANS, data_only=True)
asked = {}
for ws in wb:
    if ws.title == 'はじめに': continue
    for r in ws.iter_rows(min_row=2, values_only=True):
        if r[0]: asked[(r[3], str(r[4]).rstrip('列'))] = (ws.title, r[0], r[13] or '', ' '.join(str(x) for x in r[14:18] if x))
items = []
def add(key, q):
    if key in rep and key not in [i[0] for i in items]: items.append((key, q))
for (fn, col), (sh, no, q0, ans) in asked.items():
    k = by_base.get((fn, col))
    if not k: continue
    if '現金払い' in ans:
        add(k, '「現金払い」とのお答えでした。残りの金額と、毎月いくら・いつまで支払う予定か教えてください。もう払い終わっていれば「完済」とお書きください。')
    elif 'カード払い' in ans:
        add(k, '「カード払い」とのお答えでした。クレジットカードで一括で精算済み（お店の側で残りの支払を確認しなくてよい）という意味でよいですか？')
    elif 'どれの事かわからない' in ans:
        add(k, '前回わかりにくくてすみません。このファイルの「' + col + '列」の契約（下の契約日・商品・金額のもの）の信販会社を教えてください。わからなければ空欄で大丈夫です。')
for k, d in dates.items():
    if k in rep:
        wa = '平成%d年%d月%d日' % (int(d[:4]) - 1988, int(d[5:7]), int(d[8:]))
        add(k, '契約日を' + wa + 'と読みましたが、支払は初回の月（' + rep[k]['初回'][:7] + '）からで、何年もあいています。組み換えなどで支払予定を引き直したものでしょうか？契約日と支払予定が合っていれば「合ってます」、違っていれば正しい内容をお書きください。')
sheet_files = {l.split(',')[0] for l in open(sys.argv[5], encoding='utf-8-sig') if ',sheet,' in l}
for k, r in rep.items():
    f, col = k.rsplit('#', 1)
    if not r['確認事項'] or (os.path.basename(f), col) in asked and f not in sheet_files: continue
    if f in sheet_files or r['状態'] != '完済':
        if '2年以上' in r['確認事項'] and r['状態'] == '完済': continue
        q = []
        if '信販会社の記載なし' in r['確認事項']: q.append('信販会社が書かれていません。どこの契約ですか？')
        if '支払予定がない' in r['確認事項']: q.append('毎月の支払予定が書かれていません。支払は終わっていますか？残っていれば残りの金額と支払の予定を教えてください。')
        if '契約日を読めない' in r['確認事項']: q.append('契約日が「' + r['確認事項'].split('契約日を読めない（')[1].split('）')[0] + '」と書かれていて読めません。正しい契約日を教えてください。')
        if '2年以上' in r['確認事項']: q.append('契約日から初回の支払まで2年以上あいています。契約日か初回の月が違っていませんか？合っていれば「合ってます」とお書きください。')
        if f in sheet_files: q.insert(0, '（前回「シート(2)が正しい」とのことで、そちらのシートを読み直したら出てきた点です）')
        add(k, '\n'.join(q))
out = openpyxl.Workbook(); ws = out.active; ws.title = '追加確認'
hdr = ['No', '担当', 'お客様名', 'Excelファイル', '契約の列', '契約日（読めた分）', '商品', '元金', '回数', '初回', '最終', '状態', '今の信販会社', '前回のお答え', '確認してほしいこと', '回答', 'メモ']
ws.append(hdr)
for i, (k, q) in enumerate(items, 1):
    r = rep[k]; f, col = k.rsplit('#', 1)
    staff = os.path.basename(f).split('(')[-1].split('（')[-1].rstrip(').xlsx）')
    a = None if f in sheet_files else asked.get((os.path.basename(f), col))
    ws.append([i, staff, r['お客様名'], os.path.basename(f), col + '列', dates.get(k, r['契約日']), r['商品'], int(r['元金'] or 0), int(r['回数'] or 0), r['初回'][:7], r['最終'][:7], r['状態'], r['信販会社'], a[3] if a else '', q, '', ''])
thin = Side(style='thin', color='999999')
for c in ws[1]:
    c.font = Font(bold=True, color='FFFFFF'); c.fill = PatternFill('solid', fgColor='4F6D8F'); c.alignment = Alignment(wrap_text=True, vertical='center')
for row in ws.iter_rows(min_row=2):
    for c in row:
        c.alignment = Alignment(wrap_text=True, vertical='top'); c.border = Border(top=thin, bottom=thin, left=thin, right=thin)
    row[7].number_format = '#,##0'
    for c in row[15:17]: c.fill = PatternFill('solid', fgColor='FFF7D6')
for col, w in zip('ABCDEFGHIJKLMNOPQ', [5, 7, 14, 24, 7, 11, 12, 11, 6, 9, 9, 8, 12, 16, 52, 30, 20]):
    ws.column_dimensions[col].width = w
ws.freeze_panes = 'D2'
out.save(OUT)
print(len(items))
for i, (k, q) in enumerate(items, 1): print(i, k.rsplit('#',1)[1], rep[k]['状態'], q[:40].replace('\n',' '))
