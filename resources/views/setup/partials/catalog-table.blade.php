@if (empty($items))
    <p style="font-size:0.8125rem;color:var(--muted);">Sin resultados.</p>
@else
    <table class="catalog-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Descripción</th>
                <th>Variable .env</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td><strong>{{ $item['id'] }}</strong></td>
                    <td>{{ $item['label'] }}</td>
                    <td><code class="env-line" style="display:inline;padding:0.15rem 0.4rem;">{{ $envKey }}={{ $item['id'] }}</code></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
