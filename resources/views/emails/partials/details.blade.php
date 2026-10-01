<table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;border-collapse:collapse;table-layout:fixed;font-size:14px;line-height:1.6">
@foreach($details as $label => $value)
<tr>
<th scope="row" width="38%" style="padding:12px 12px 12px 0;text-align:left;vertical-align:top;border-bottom:1px solid #e1e6ea;color:#52616d;font-weight:400;overflow-wrap:anywhere;word-break:break-word">{{ $label }}</th>
<td style="padding:12px 0;border-bottom:1px solid #e1e6ea;color:#202b33;font-weight:600;vertical-align:top;overflow-wrap:anywhere;word-break:break-word">{{ $value }}</td>
</tr>
@endforeach
</table>
