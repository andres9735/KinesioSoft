@php($pendiente = $estado === 'Pendiente')
<span style="display:inline-block;padding:5px 12px;border-radius:18px;background-color:{{ $pendiente ? '#fff1d6' : '#e6f5ed' }};color:{{ $pendiente ? '#7e450d' : '#246147' }};font-size:13px;line-height:16px;font-weight:700;">{{ $estado }}</span>
