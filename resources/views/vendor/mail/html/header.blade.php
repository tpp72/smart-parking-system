@props(['url'])
{{-- หัวอีเมล: ตรา "P" สีครามแบบเดียวกับแถบเมนูของเว็บ + ชื่อระบบ (ไม่ใช้รูปภาพจากภายนอก) --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto;">
<tr>
<td style="width: 32px; height: 32px; background-color: #3438B8; border-radius: 2px; color: #FFFFFF; font-size: 16px; font-weight: 700; line-height: 32px; text-align: center; font-family: 'Martian Mono', ui-monospace, Menlo, Consolas, monospace;">P</td>
<td style="padding-left: 10px; color: #15171A; font-size: 17px; font-weight: 600; line-height: 32px;">Smart Parking</td>
</tr>
</table>
</a>
</td>
</tr>
