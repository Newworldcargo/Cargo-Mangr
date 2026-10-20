<!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:24px}
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#263441}
.compact-document{border:0!important;padding:0!important;max-width:none!important;min-height:0!important;font-size:10px!important}
.compact-document h1{font-size:20px}.compact-document h2{font-size:10px}.compact-document p{overflow-wrap:break-word}
.invoice-heading,.invoice-parties,.invoice-bottom-fields{page-break-inside:avoid}
.sales-lines{width:100%;border-collapse:collapse;table-layout:fixed;font-size:9px!important}
.sales-lines th,.sales-lines td{text-align:right;vertical-align:top;border-bottom:1px solid #dde3e9;padding:7px 4px!important;overflow-wrap:break-word}
.sales-lines td:first-child,.sales-lines th:first-child{text-align:left;width:32%}
.sales-lines tr{page-break-inside:avoid}.sales-lines thead{display:table-header-group}.d-block{display:block;font-size:8px}
.sales-document-bottom{page-break-inside:avoid}
.sales-totals{display:block!important;width:46%!important;margin-left:54%!important;font-size:10px!important}
.sales-totals dt{float:left;width:55%;margin:0;padding:5px 0;clear:left}.sales-totals dd{margin:0 0 0 55%;text-align:right;padding:5px 0}
.sales-totals .sales-grand{font-size:12px!important}.invoice-footer{display:block!important;page-break-inside:avoid}.invoice-footer span{display:inline-block;width:49%;font-size:9px}.invoice-footer span:last-child{text-align:right}
.sales-terms p{white-space:pre-wrap;line-height:1.5}.small{font-size:9px}
</style></head><body>
@include('billing::document',['pdfMode'=>true,'originalDocument'=>true])
</body></html>
