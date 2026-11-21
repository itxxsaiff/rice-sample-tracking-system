<table class="summary">
    <tr>
        @foreach ($summary as $label => $value)
            <td><div class="k">{{ $label }}</div><div class="v">{{ $value }}</div></td>
        @endforeach
    </tr>
</table>
