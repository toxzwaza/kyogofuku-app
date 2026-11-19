import datetime, os
import openpyxl, xlrd

def read_book(path):
    """[(sheet_name, rows)] rows = list of lists (values; dates as datetime)"""
    out = []
    if path.lower().endswith('.xlsx'):
        wb = openpyxl.load_workbook(path, data_only=True, read_only=True)
        for ws in wb.worksheets:
            rows = [list(r) for r in ws.iter_rows(values_only=True)]
            out.append((ws.title, rows))
        wb.close()
    else:
        wb = xlrd.open_workbook(path)
        for sh in wb.sheets():
            rows = []
            for r in range(sh.nrows):
                row = []
                for c in range(sh.ncols):
                    cell = sh.cell(r, c)
                    v = cell.value
                    if cell.ctype == xlrd.XL_CELL_DATE:
                        try: v = xlrd.xldate.xldate_as_datetime(v, wb.datemode)
                        except Exception: pass
                    elif cell.ctype == xlrd.XL_CELL_EMPTY: v = None
                    elif v == '': v = None
                    row.append(v)
                rows.append(row)
            out.append((sh.name, rows))
    return out
