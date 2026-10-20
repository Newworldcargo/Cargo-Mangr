<style>
.compact-document{box-sizing:border-box;background:white;color:#263441;letter-spacing:0;max-width:210mm;margin:0 auto;padding:28px 32px;border:1px solid #dce3e9;border-radius:0;min-height:260mm;font-size:12px}
.compact-sales-page>.sales-page-heading{max-width:210mm;margin:0 auto 12px;gap:12px}.compact-sales-page .sales-back{margin:0 auto 0 0}
.compact-document .invoice-heading{display:table;width:100%;table-layout:fixed;border-bottom:3px solid #f6c620;padding-bottom:16px;margin-bottom:16px}
.invoice-company,.invoice-heading-meta{display:table-cell;width:50%;vertical-align:top}.invoice-heading-meta{text-align:right;padding-left:28px}.invoice-heading-meta h1{font-size:22px;color:#012642;margin:0 0 5px;font-weight:700}.invoice-heading-meta>p{margin:0 0 12px}
.invoice-brand{background:#012642;display:inline-block;padding:0 8px;margin-bottom:8px}.invoice-brand img{width:125px;height:50px;object-fit:contain}.invoice-company>strong{display:block;font-size:12px}.compact-document p{margin:4px 0;overflow-wrap:anywhere}
.compact-document .invoice-heading-meta label{display:grid;grid-template-columns:1fr 145px;align-items:center;gap:6px;text-align:left}.compact-document .invoice-heading-meta small{display:block;margin-top:6px}
.compact-document .form-control{min-height:30px;height:30px;padding:3px 5px;font-size:12px;border:1px solid #aab7c2;border-radius:2px;background:white;box-shadow:none;max-width:100%;color:#263441}
.compact-document .form-control[readonly],.compact-document .form-control:disabled{background:#f1f3f5;border-style:dashed;color:#526271}
.compact-document textarea.form-control{height:auto;resize:vertical}.compact-document .form-control:hover{border-color:#c7d7e6}.compact-document .form-control:focus{border-color:#012642;background:#f5f9ff;outline:2px solid #bdd8ef;outline-offset:0}
.compact-document label{font-size:11px;margin:0 0 4px;font-weight:400}.compact-document h2{font-size:11px;text-transform:uppercase;color:#526271;border:0;padding:0;margin:0 0 8px}
.compact-document .sr-only{width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important}
.invoice-parties,.invoice-bottom-fields{display:table;width:100%;table-layout:fixed;margin:12px 0}.invoice-parties>section,.invoice-bottom-fields>section{display:table-cell;width:50%;vertical-align:top;padding-right:20px}.invoice-parties>section:last-child,.invoice-bottom-fields>section:last-child{padding:0 0 0 16px}
.compact-document .sales-tabs{margin:0 0 18px;gap:20px}.compact-document .sales-tabs .nav-link{padding:7px 0;font-size:12px}
.compact-document .sales-section-heading{margin:16px 0 4px}.compact-document .sales-section-heading h2{margin:0}.compact-document #catalogue-status{font-size:11px;color:#7f4e10;margin-bottom:8px}
.invoice-extra{border-top:1px solid #e6ebef;padding:7px 0;margin:4px 0}.invoice-extra summary,.invoice-line-options summary{cursor:pointer;color:#526271;font-size:11px;min-height:26px;line-height:26px}.invoice-extra .row{margin-top:8px;margin-bottom:0!important}.invoice-extra .mb-3{margin-bottom:6px!important}
.invoice-line-head,.compact-document .sales-service-line{display:grid;grid-template-columns:24px minmax(0,1fr) 76px 90px 92px 32px;gap:8px;align-items:start}
.invoice-line-head{padding:8px 4px;background:#012642;color:white;font-size:11px}.invoice-line-head>span:nth-child(4),.invoice-line-head>span:nth-child(5){text-align:right}
.compact-document .sales-lines-editor{overflow:visible;counter-reset:invoice-line}.compact-document .sales-service-line{padding:8px 4px 4px;margin:0;border-bottom:1px solid #dce3e9;counter-increment:invoice-line}
.invoice-line-number{padding-top:7px;color:#71808d}.invoice-line-number:before{content:counter(invoice-line)}.invoice-description{min-width:0}.invoice-line-amount{text-align:right;font-weight:600;font-variant-numeric:tabular-nums;overflow-wrap:anywhere;padding-top:7px}
.compact-document [name$="[unit_price]"]{text-align:right;font-variant-numeric:tabular-nums}.compact-document .sales-service-line .btn{min-height:30px;padding:3px;width:30px;border:0!important}.invoice-mobile-label{display:none}
.invoice-line-options{grid-column:2/-1}.invoice-line-options-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
.compact-document .tariff-price-label{display:block;font-size:10px}
.invoice-customer-search,.service-search-wrap{position:relative}.compact-document #customer-results:not(:empty),.service-suggestions:not([hidden]){position:absolute;top:100%;left:0;right:0;z-index:20;max-height:220px;overflow-y:auto;box-shadow:0 5px 12px #17232d26;background:white;border:1px solid #ccd6df;font-size:12px}.service-suggestions .list-group-item{padding:8px;text-align:left;white-space:normal;color:#263441;background:white}
.compact-document #add-customer,.compact-document #add-line{min-height:30px;font-size:12px;padding:3px 6px}.compact-document #add-customer{margin:3px 0!important}
.compact-document #add-line{color:#012642!important}.compact-document #add-line:hover{background:#edf4fb}
.compact-document .sales-preview-summary{display:flex;flex-direction:column;align-items:flex-end;border:0;padding:4px 0 16px;margin:0;min-height:135px}
.compact-document #totals-status{font-size:11px;min-height:18px;margin:0 0 5px}.compact-document .sales-totals{width:310px;max-width:100%;font-size:12px;gap:5px 10px;break-inside:avoid}.compact-document .sales-totals .sales-grand{background:#fff3c8;color:#012642;padding:9px 6px;font-size:15px;border-top:2px solid #f6c620}.compact-document .is-calculating{opacity:.5}
.compact-document .invoice-footer{border-top:2px solid #f6c620;display:flex;justify-content:space-between;gap:16px;margin-top:18px;padding-top:12px;font-size:10px;color:#526271}
.compact-document .sales-toolbar{margin-top:12px;padding-top:12px}.compact-document .sales-lines{font-size:11px}.compact-document .sales-lines th,.compact-document .sales-lines td{padding:7px 5px}.compact-document .sales-lines th{background:#012642;color:white}
.compact-document .sales-document-bottom{display:block;margin:10px 0 16px}.compact-document .sales-document-bottom .sales-totals{margin-left:auto}.compact-document .sales-terms p{line-height:1.5;color:#526271;font-size:11px}
@media(max-width:650px){.compact-sales-page{padding:10px!important}.compact-document{padding:16px 12px;min-height:0}.invoice-company,.invoice-heading-meta{display:block;width:100%;padding:0;text-align:left}.invoice-heading-meta{margin-top:14px}.compact-document .invoice-heading-meta label{grid-template-columns:1fr 155px}.invoice-parties,.invoice-bottom-fields{display:block}.invoice-parties>section,.invoice-bottom-fields>section{display:block;width:100%;padding:0!important;margin-bottom:12px}.compact-document .form-control{min-height:40px;height:40px;font-size:14px}.compact-document textarea.form-control{height:auto}.invoice-line-head{display:none}.compact-document .sales-service-line{grid-template-columns:24px minmax(0,1fr) minmax(0,1fr) 36px;gap:6px;padding-top:12px}.invoice-description{grid-column:2/4}.compact-document .sales-service-line>[data-remove]{grid-column:4;grid-row:1;min-height:40px}.compact-document .sales-service-line>div:nth-of-type(2){grid-column:2}.compact-document .sales-service-line>div:nth-of-type(3){grid-column:3}.invoice-mobile-label{display:block}.invoice-line-amount{grid-column:2/4}.invoice-line-amount:before{content:'Amount ';font-weight:400;color:#526271}.invoice-line-options{grid-column:2/-1}.invoice-line-options-grid{grid-template-columns:1fr 1fr}.invoice-line-options summary,.invoice-extra summary{min-height:40px;line-height:20px;padding:10px 0}.compact-document .sales-preview-summary{min-height:145px}.compact-document .invoice-footer{flex-direction:column}.compact-document .sales-totals{width:100%}.compact-document .sales-service-line label{grid-column:auto}.compact-document .sales-tabs{gap:12px}.compact-document .sales-tabs .nav-link{white-space:normal}}
@media(max-width:650px){.compact-document .form-control{min-height:44px;height:44px;font-size:16px}.compact-document .sales-service-line{grid-template-columns:24px minmax(0,1fr) minmax(0,1fr) 44px}.compact-document .sales-service-line>[data-remove]{width:44px;min-height:44px}.compact-sales-page .btn,.compact-document .service-suggestions button{min-height:44px}.compact-document #add-customer,.compact-document #add-line{min-height:44px}}
/* Screen editing follows the admin content width; paper sizing is for export only. */
@unless($pdfMode ?? false)
@media screen{
.compact-document{width:100%;max-width:none;min-height:0;font-size:14px}
.compact-sales-page>.sales-page-heading{max-width:none}
.compact-document .invoice-heading-meta h1{font-size:26px}
.compact-document .invoice-brand img{width:150px;height:60px}
.compact-document .invoice-company>strong{font-size:14px}
.compact-document label,.compact-document h2,.compact-document .sales-tabs .nav-link,.compact-document .invoice-line-head,.compact-document .sales-lines,.compact-document .sales-lines th,.compact-document .sales-lines td,.compact-document .sales-terms p{font-size:14px}
.compact-document .invoice-extra summary,.compact-document .invoice-line-options summary,.compact-document #catalogue-status,.compact-document #totals-status,.compact-document .tariff-price-label,.compact-document .invoice-footer,.compact-document small{font-size:13px}
.compact-document #add-customer,.compact-document #add-line{font-size:14px;min-height:40px}
.compact-document #customer-results,.compact-document .service-suggestions{font-size:14px}
.compact-document .sales-totals{width:360px;font-size:14px}
.compact-document .sales-totals .sales-grand{font-size:18px}
.compact-document .sales-totals dt{color:#526271}
.compact-document .invoice-heading-meta label{grid-template-columns:1fr 165px}
}
@media screen and (min-width:651px){
.compact-document{padding:24px}
.compact-document .form-control{font-size:14px;min-height:40px;height:40px;padding:6px 8px}
.compact-document textarea.form-control{height:auto}
.invoice-line-head,.compact-document .sales-service-line{grid-template-columns:28px minmax(0,1fr) 90px 110px 115px 40px}
.compact-document .sales-service-line .btn{width:40px;min-height:40px}
}
@endunless
@media print{.compact-document{border:0;padding:0;max-width:none;min-height:0}.invoice-heading,.invoice-parties,.invoice-footer,tr{break-inside:avoid;page-break-inside:avoid}thead{display:table-header-group}.invoice-heading-meta h1{font-size:20px}}
</style>
