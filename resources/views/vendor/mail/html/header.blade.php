@props(['url'])
{{-- หัวอีเมล: โลโก้จาก config/brand.php (ค่าเริ่มต้น = ตรา "P" แบบเดียวกับแถบเมนู ไม่ใช้รูปจากภายนอก) --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto;">
<tr>
@if (config('brand.logo'))
<td><img src="{{ url(config('brand.logo')) }}" alt="{{ config('brand.name') }}" height="{{ config('brand.logo_height') }}" style="display: block; height: {{ config('brand.logo_height') }}px; width: auto; border: 0;"></td>
@else
<td style="width: 32px; height: 32px; background-color: #3438B8; border-radius: 2px; color: #FFFFFF; font-size: 16px; font-weight: 700; line-height: 32px; text-align: center; font-family: 'Martian Mono', ui-monospace, Menlo, Consolas, monospace;">P</td>
@endif
<td style="padding-left: 10px; color: #15171A; font-size: 17px; font-weight: 600; line-height: 32px;">{{ config('brand.name') }}</td>
</tr>
</table>
</a>
</td>
</tr>
