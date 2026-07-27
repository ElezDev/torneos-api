<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Planilla #{{ $match->id }}</title>
  <style>
    @page { margin: 28px 32px; }
    body {
      font-family: DejaVu Sans, sans-serif;
      color: #1a1a1a;
      font-size: 11px;
      line-height: 1.35;
    }
    .header {
      border-bottom: 3px solid #0f6b4c;
      padding-bottom: 12px;
      margin-bottom: 16px;
    }
    .brand {
      font-size: 20px;
      font-weight: 700;
      letter-spacing: 0.5px;
      color: #0f6b4c;
      margin: 0;
    }
    .subtitle {
      margin: 2px 0 0;
      color: #666;
      font-size: 10px;
    }
    .vs {
      text-align: center;
      margin: 10px 0 16px;
    }
    .vs h1 {
      margin: 0;
      font-size: 18px;
    }
    .meta {
      color: #555;
      font-size: 10px;
      margin-top: 4px;
    }
    .score {
      font-size: 28px;
      font-weight: 700;
      color: #0f6b4c;
      margin-top: 6px;
    }
    .grid {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    .grid td {
      vertical-align: top;
      width: 50%;
      padding: 0 8px;
    }
    .team-box {
      border: 1px solid #d8d8d8;
      border-radius: 6px;
      overflow: hidden;
      margin-bottom: 12px;
    }
    .team-title {
      background: #0f6b4c;
      color: #fff;
      padding: 7px 10px;
      font-weight: 700;
      font-size: 12px;
    }
    .team-sub {
      background: #f3f7f5;
      padding: 5px 10px;
      font-size: 10px;
      color: #444;
      border-bottom: 1px solid #e5e5e5;
    }
    table.players {
      width: 100%;
      border-collapse: collapse;
    }
    table.players th,
    table.players td {
      padding: 4px 8px;
      border-bottom: 1px solid #eee;
      text-align: left;
      font-size: 10px;
    }
    table.players th {
      background: #fafafa;
      color: #666;
      font-weight: 600;
    }
    .badge {
      display: inline-block;
      padding: 1px 5px;
      border-radius: 3px;
      font-size: 9px;
      background: #e8f3ee;
      color: #0f6b4c;
    }
    .section-title {
      margin: 14px 0 6px;
      font-size: 12px;
      font-weight: 700;
      color: #0f6b4c;
      border-bottom: 1px solid #cfe3da;
      padding-bottom: 3px;
    }
    table.events {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
    }
    table.events th,
    table.events td {
      padding: 5px 7px;
      border-bottom: 1px solid #eee;
      font-size: 10px;
      text-align: left;
    }
    table.events th {
      background: #f7f7f7;
      color: #555;
    }
    .footer {
      margin-top: 22px;
      border-top: 1px solid #ddd;
      padding-top: 14px;
    }
    .signs {
      width: 100%;
      border-collapse: collapse;
    }
    .signs td {
      width: 33.33%;
      text-align: center;
      padding: 28px 8px 0;
      font-size: 10px;
      color: #555;
    }
    .sign-line {
      border-top: 1px solid #888;
      margin: 0 auto 6px;
      width: 80%;
    }
    .obs {
      background: #fafafa;
      border: 1px solid #ececec;
      padding: 8px 10px;
      min-height: 40px;
      border-radius: 4px;
      white-space: pre-wrap;
    }
  </style>
</head>
<body>
  @php
    $typeLabels = [
      'goal' => 'Gol',
      'ownGoal' => 'Autogol',
      'yellowCard' => 'Amarilla',
      'redCard' => 'Roja',
      'secondYellow' => '2ª amarilla',
      'substitution' => 'Cambio',
    ];
    $fmtPlayer = fn ($p) => $p ? trim(($p->first_name ?? '').' '.($p->last_name ?? '')) : '—';
  @endphp

  <div class="header">
    <p class="brand">TorneosApp</p>
    <p class="subtitle">Planilla oficial de partido · {{ $match->tournament?->name }}</p>
  </div>

  <div class="vs">
    <h1>{{ $match->homeTeam?->name ?? 'Local' }} vs {{ $match->awayTeam?->name ?? 'Visitante' }}</h1>
    <div class="meta">
      @if($match->scheduled_at) {{ $match->scheduled_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · @endif
      {{ $match->venue?->name ?? 'Sede por confirmar' }}
      @if($match->group) · {{ $match->group->name }} @endif
      @if($match->round_name) · {{ $match->round_name }} @endif
    </div>
    <div class="score">
      {{ $match->home_score ?? '—' }} — {{ $match->away_score ?? '—' }}
    </div>
    <div class="meta">
      Árbitro: {{ $match->referee_name ?: '________________' }}
      · Estado: {{ $match->status }}
    </div>
  </div>

  <table class="grid">
    <tr>
      @foreach([
        ['sheet' => $homeSheet, 'label' => 'Local'],
        ['sheet' => $awaySheet, 'label' => 'Visitante'],
      ] as $side)
        <td>
          <div class="team-box">
            <div class="team-title">{{ $side['sheet']?->team?->name ?? $side['label'] }}</div>
            <div class="team-sub">
              Delegado: {{ $side['sheet']?->delegate_name ?: '________________' }}
            </div>
            <table class="players">
              <thead>
                <tr>
                  <th style="width:28px">Nº</th>
                  <th>Jugador</th>
                  <th style="width:70px">CC</th>
                  <th style="width:55px">Rol</th>
                </tr>
              </thead>
              <tbody>
                @forelse(($side['sheet']?->players ?? collect())->sortByDesc('is_starter') as $row)
                  <tr>
                    <td>{{ $row->jersey_number ?? '—' }}</td>
                    <td>{{ $fmtPlayer($row->player) }}</td>
                    <td>{{ $row->player?->document_id ?? '—' }}</td>
                    <td>@if($row->is_starter)<span class="badge">Titular</span>@else Banco @endif</td>
                  </tr>
                @empty
                  <tr><td colspan="4">Sin nómina cargada</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </td>
      @endforeach
    </tr>
  </table>

  <div class="section-title">Incidencias del partido</div>
  <table class="events">
    <thead>
      <tr>
        <th style="width:40px">Min</th>
        <th style="width:90px">Tipo</th>
        <th>Detalle</th>
        <th style="width:120px">Equipo</th>
      </tr>
    </thead>
    <tbody>
      @forelse($events as $event)
        <tr>
          <td>{{ $event->minute !== null ? $event->minute."'" : '—' }}</td>
          <td>{{ $typeLabels[$event->type] ?? $event->type }}</td>
          <td>
            {{ $fmtPlayer($event->player) }}
            @if($event->type === 'substitution' && $event->relatedPlayer)
              → entra {{ $fmtPlayer($event->relatedPlayer) }}
            @endif
            @if($event->notes)
              <span style="color:#777">({{ $event->notes }})</span>
            @endif
          </td>
          <td>{{ $event->team?->name ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="4">Sin incidencias registradas</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="section-title">Observaciones</div>
  <div class="obs">{{ $match->notes ?: ($homeSheet?->observations ?: $awaySheet?->observations ?: 'Sin observaciones.') }}</div>

  <div class="footer">
    <table class="signs">
      <tr>
        <td>
          <div class="sign-line"></div>
          Delegado local
        </td>
        <td>
          <div class="sign-line"></div>
          Árbitro
        </td>
        <td>
          <div class="sign-line"></div>
          Delegado visitante
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
