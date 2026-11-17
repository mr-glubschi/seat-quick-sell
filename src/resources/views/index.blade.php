@extends('web::layouts.grids.12')

@section('title', trans('quicksell::global.title'))
@section('page_header', trans('quicksell::global.title'))
@section('page_description', trans('quicksell::global.description'))

@section('full')
  <div class="row">
    <div class="col-lg-4 col-xl-3">
      <div class="card">
        <div class="card-body">
          <div class="form-group">
            <label for="qs-market">{{ trans('quicksell::global.market') }}</label>
            <select id="qs-market" class="form-control" style="width: 100%">
              @if($preselect)
                <option value="{{ $preselect['id'] }}" selected>{{ $preselect['name'] }}</option>
              @endif
            </select>
          </div>
          <div class="form-group">
            <label for="qs-seller">{{ trans('quicksell::global.character') }}</label>
            <select id="qs-seller" class="form-control">
              @foreach($characters as $id => $name)
                <option value="{{ $id }}" @if($id === $mainId) selected @endif>{{ $name }}@if($id === $mainId) ({{ trans('quicksell::global.main') }})@endif</option>
              @endforeach
            </select>
            <small class="form-text text-muted">{{ trans('quicksell::global.character_help') }}</small>
          </div>
          <div class="form-group">
            <label for="qs-items">{{ trans('quicksell::global.items') }}</label>
            <textarea id="qs-items" class="form-control text-monospace" rows="10" spellcheck="false"></textarea>
            <small class="form-text text-muted">{{ trans('quicksell::global.items_help') }}</small>
          </div>
          <div class="d-flex">
            <button id="qs-check" type="button" class="btn btn-primary flex-grow-1 mr-2">
              <i class="fas fa-search-dollar"></i> {{ trans('quicksell::global.check') }}
            </button>
            <button id="qs-reset" type="button" class="btn btn-outline-secondary" title="{{ trans('quicksell::global.reset_help') }}">
              <i class="fas fa-eraser"></i> {{ trans('quicksell::global.reset') }}
            </button>
          </div>
          <div id="qs-progress" class="mt-2 small text-muted d-none"></div>
          <div id="qs-error" class="alert alert-danger mt-2 d-none"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">{{ trans('quicksell::global.fees') }}</h3>
          <span id="qs-fees-saved" class="badge badge-info float-right d-none">{{ trans('quicksell::global.fees_saved') }}</span>
        </div>
        <div class="card-body">
          <p class="small text-muted">{{ trans('quicksell::global.fees_help') }}</p>
          <div class="form-row">
            @foreach(\Glubschi\Seat\QuickSell\Pricing\Fees::FIELDS as $field)
              <div class="form-group col-6">
                <label class="small mb-1" for="qs-fee-{{ $field }}">{{ trans('quicksell::global.fee_' . $field) }}</label>
                <input id="qs-fee-{{ $field }}" data-fee="{{ $field }}" type="number" step="0.001" min="0" max="100"
                       class="form-control form-control-sm" @if($field === 'broker_pct') placeholder="{{ trans('quicksell::global.broker_unknown') }}" @endif>
              </div>
            @endforeach
          </div>
          <button id="qs-reset-fees" type="button" class="btn btn-sm btn-outline-secondary">{{ trans('quicksell::global.reset_fees') }}</button>
          <div id="qs-skills" class="small mt-3 d-none">
            <div class="font-weight-bold mb-1">{{ trans('quicksell::global.skills') }} <span id="qs-skills-char" class="font-weight-normal text-muted"></span></div>
            <div id="qs-skills-list"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-8 col-xl-9">
      <div class="row">
        @foreach(['instant', 'reprocess', 'market', 'mix'] as $way)
          <div class="col-sm-6 col-xl-3">
            <div class="card qs-tile qs-{{ $way }}" data-tile="{{ $way }}">
              <div class="card-body py-2">
                <div class="qs-tile-label">{{ trans('quicksell::global.way_' . $way) }}</div>
                <div class="qs-tile-net" data-tile-net>–</div>
                <div class="small text-muted">{{ trans('quicksell::global.way_' . $way . '_help') }}</div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title" id="qs-market-name">{{ trans('quicksell::global.items') }}</h3>
          <div class="card-tools">
            <button id="qs-copy" type="button" class="btn btn-sm btn-success" disabled title="{{ trans('quicksell::global.copy_help') }}">
              <i class="fas fa-copy"></i> <span>{{ trans('quicksell::global.copy') }}</span> <span id="qs-copy-count" class="badge badge-light"></span>
            </button>
            <button id="qs-copy-all" type="button" class="btn btn-sm btn-outline-success" disabled>
              <span>{{ trans('quicksell::global.copy_all') }}</span>
            </button>
          </div>
        </div>
        <div class="card-body p-0">
          <div id="qs-denied" class="alert alert-warning m-2 d-none">{{ trans('quicksell::global.denied') }}</div>
          <div id="qs-slow" class="alert alert-warning m-2 d-none">{{ trans('quicksell::global.fetch_slow') }}</div>
          <div class="table-responsive">
            <table id="qs-table" class="table table-sm table-hover mb-0">
              <thead>
                <tr>
                  <th></th>
                  <th class="text-nowrap">{{ trans('quicksell::global.col_item') }}</th>
                  <th class="text-right text-nowrap">{{ trans('quicksell::global.col_qty') }}</th>
                  <th class="text-right text-nowrap">{{ trans('quicksell::global.col_station_sell') }}</th>
                  <th class="text-right text-nowrap">{{ trans('quicksell::global.col_jita_sell') }}</th>
                  <th class="text-right text-nowrap">{{ trans('quicksell::global.col_price') }}</th>
                  <th class="text-right text-nowrap">{{ trans('quicksell::global.col_vs_jita') }}</th>
                  <th class="text-right text-nowrap qs-head-instant">{{ trans('quicksell::global.way_instant') }}</th>
                  <th class="text-right text-nowrap qs-head-reprocess">{{ trans('quicksell::global.way_reprocess') }}</th>
                  <th class="text-right text-nowrap qs-head-market">{{ trans('quicksell::global.way_market') }}</th>
                  <th class="text-nowrap">{{ trans('quicksell::global.col_best') }}</th>
                </tr>
              </thead>
              <tbody id="qs-rows">
                <tr class="qs-empty"><td colspan="11" class="text-center text-muted py-4">{{ trans('quicksell::global.empty') }}</td></tr>
              </tbody>
            </table>
          </div>
          <div id="qs-unknown" class="alert alert-light m-2 small d-none">
            <strong>{{ trans('quicksell::global.unknown') }}</strong>
            <ul class="mb-0"></ul>
          </div>
          <textarea id="qs-import" class="form-control text-monospace d-none" rows="4" readonly></textarea>
        </div>
        <div class="card-footer small text-muted">
          <div class="d-flex flex-wrap align-items-center">
            <span class="font-weight-bold mr-3 mb-1">{{ trans('quicksell::global.legend') }}</span>
            <span class="mr-3 mb-1 text-nowrap"><span class="qs-swatch table-info"></span>{{ trans('quicksell::global.legend_no_offer') }}</span>
            <span class="mr-3 mb-1 text-nowrap"><i class="fas fa-user-tag text-primary mr-1"></i>{{ trans('quicksell::global.legend_own_order') }}</span>
            <span class="mr-3 mb-1 text-nowrap"><i class="fas fa-arrow-circle-up text-warning mr-1"></i>{{ trans('quicksell::global.legend_buy_above') }}</span>
            <span class="mr-3 mb-1 text-nowrap"><i class="fas fa-exclamation-triangle text-danger mr-1"></i>{{ trans('quicksell::global.legend_no_data') }}</span>
            <span class="mr-3 mb-1 text-nowrap"><span class="text-success">+%</span> / <span class="text-danger">−%</span> {{ trans('quicksell::global.legend_pct') }}</span>
            <span class="mb-1 text-nowrap"><span class="qs-swatch qs-best-swatch"></span>{{ trans('quicksell::global.legend_best') }}</span>
          </div>
          <div class="mt-1">{{ trans('quicksell::global.notes') }}</div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('head')
  <style>
    .qs-swatch { display: inline-block; width: 1em; height: 1em; margin-right: .3em; vertical-align: -.15em; border: 1px solid rgba(0,0,0,.15); }
    #qs-rows .eve-icon { margin-right: .4em; width: 24px; height: 24px; }
    #qs-table { font-size: .9rem; }
    #qs-table td.qs-way { line-height: 1.2; }
    #qs-table td.qs-way small { display: block; color: #6c757d; }
    #qs-table td.qs-best, .qs-best-swatch { background: rgba(40, 167, 69, .15); }
    #qs-table td.qs-best .qs-net { font-weight: 700; }
    .qs-tile { border-top: 3px solid var(--qs-c); }
    .qs-tile-label { font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--qs-c); }
    .qs-tile-net { font-size: 1.3rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .qs-tile.qs-win { background: rgba(40, 167, 69, .08); }
    .qs-instant { --qs-c: #d9822b; } .qs-reprocess { --qs-c: #6f42c1; } .qs-market { --qs-c: #28a745; } .qs-mix { --qs-c: #007bff; }
    .qs-pill { font-size: .75rem; color: #fff; padding: .15em .55em; border-radius: 1em; background: var(--qs-c); white-space: nowrap; }
  </style>
@endpush

@push('javascript')
  <script>
    $(function () {
      var urls = {
        markets: @json(route('seatquicksell::markets')),
        quote: @json(route('seatquicksell::quote')),
        status: @json(route('seatquicksell::status')),
        fees: @json(route('seatquicksell::fees')),
        feesReset: @json(route('seatquicksell::fees.reset'))
      };
      var t = @json(trans('quicksell::global'));
      var WAYS = ['instant', 'reprocess', 'market'];
      var POLL_MS = 1500, MAX_WAIT_MS = 120000;
      var lastQuote = null, busy = false;

      // Market choice with SeAT's select2: docked places, last market and hubs on open, search from 3 letters.
      $('#qs-market').select2({
        placeholder: t.market_placeholder,
        ajax: {
          url: urls.markets,
          dataType: 'json',
          delay: 250,
          data: function (params) { return { q: params.term || '' }; },
          processResults: function (data) { return { results: data.results }; }
        }
      }).on('change', loadFees);

      function showError(message) {
        $('#qs-error').text(message || t.error).removeClass('d-none');
      }

      function errorMessage(xhr) {
        return (xhr.responseJSON && xhr.responseJSON.message) || t.error;
      }

      function setBusy(on) {
        busy = on;
        $('#qs-check').prop('disabled', on);
        if (!on) $('#qs-progress').addClass('d-none');
      }

      // Fees: entered by hand, the last values of each market come back when it is chosen.
      function showFees(fees, saved) {
        $('[data-fee]').each(function () {
          var v = fees[$(this).data('fee')];
          $(this).val(v === null || v === undefined ? '' : Math.round(v * 1000) / 1000);
        });
        $('#qs-fees-saved').toggleClass('d-none', !saved);
      }

      function loadFees() {
        var market = $('#qs-market').val();
        if (!market) return;
        $.get(urls.fees, { market: market }).done(function (d) { showFees(d.fees, d.saved); });
      }

      function enteredFees() {
        var fees = {};
        $('[data-fee]').each(function () { fees[$(this).data('fee')] = $(this).val(); });
        return fees;
      }

      function quote(noFetch, slow) {
        var market = $('#qs-market').val();
        if (!market) { showError(t.error_market); return; }
        $('#qs-error').addClass('d-none');
        setBusy(true);
        $.post(urls.quote, {
          market: market,
          items: $('#qs-items').val(),
          character_id: $('#qs-seller').val(),
          fees: enteredFees(),
          no_fetch: noFetch ? 1 : 0
        }).done(function (data) {
          if (data.status === 'pending') {
            wait(data.batches, Date.now());
          } else {
            setBusy(false);
            render(data, slow);
          }
        }).fail(function (xhr) {
          setBusy(false);
          showError(errorMessage(xhr));
          if (xhr.responseJSON && xhr.responseJSON.unknown) showUnknown(xhr.responseJSON.unknown);
        });
      }

      // Waits for the SeAT jobs, then asks again without fetching. Rows still missing after MAX_WAIT_MS are marked.
      function wait(batches, started) {
        $.get(urls.status, { batches: batches }).done(function (s) {
          $('#qs-progress').text(t.fetching.replace(':done', s.done).replace(':total', s.total)).removeClass('d-none');
          var late = Date.now() - started > MAX_WAIT_MS;
          if (s.finished || late) {
            quote(true, late && !s.finished);
          } else {
            setTimeout(function () { wait(batches, started); }, POLL_MS);
          }
        }).fail(function (xhr) { setBusy(false); showError(errorMessage(xhr)); });
      }

      function cell(text, cls) {
        var td = document.createElement('td');
        td.className = 'text-right text-nowrap' + (cls ? ' ' + cls : '');
        td.textContent = text === null || text === undefined ? '–' : text;
        return td;
      }

      function icon(cls, title) {
        var i = document.createElement('i');
        i.className = cls + ' mr-1';
        i.title = title;
        return i;
      }

      // One way of a row: net ISK, and below it what it is made of.
      function wayCell(r, way) {
        var td = document.createElement('td');
        td.className = 'text-right text-nowrap qs-way' + (r.best === way ? ' qs-best' : '');
        var w = r.ways_fmt[way];
        if (!w) { td.textContent = '–'; return td; }
        var net = document.createElement('span');
        net.className = 'qs-net';
        net.textContent = w.net;
        td.appendChild(net);
        var detail = document.createElement('small');
        if (way === 'instant') {
          detail.textContent = w.per_unit + ' / ' + t.unit + (r.ways.instant.sold < r.quantity ? ' · ' + w.sold + ' ' + t.of + ' ' + r.fmt.quantity : '');
        } else if (way === 'reprocess') {
          detail.textContent = w.yield + ' % · ' + w.per_unit + ' / ' + t.unit;
        } else {
          detail.textContent = w.per_unit + ' / ' + t.unit;
        }
        td.appendChild(detail);
        return td;
      }

      function render(data, slow) {
        lastQuote = data;
        $('#qs-copy, #qs-copy-all').prop('disabled', false);
        $('#qs-copy-count').text(data.import_count);
        $('#qs-market-name').text(data.market.name);
        $('#qs-denied').toggleClass('d-none', !data.market.denied);
        $('#qs-slow').toggleClass('d-none', !slow);

        var body = document.getElementById('qs-rows');
        body.innerHTML = '';
        data.rows.forEach(function (r) {
          var tr = document.createElement('tr');
          if (r.flags.no_offer) tr.className = 'table-info';
          var flags = document.createElement('td');
          flags.className = 'text-nowrap';
          if (r.flags.no_offer) flags.appendChild(icon('fas fa-anchor text-info', t.flag_no_offer));
          if (r.flags.own_order) flags.appendChild(icon('fas fa-user-tag text-primary', t.flag_own_order));
          if (r.flags.buy_above) flags.appendChild(icon('fas fa-arrow-circle-up text-warning', t.flag_buy_above));
          if (r.flags.no_data) flags.appendChild(icon('fas fa-exclamation-triangle text-danger', t.flag_no_data));
          tr.appendChild(flags);
          var name = document.createElement('td');
          name.className = 'text-nowrap';
          // The icon is SeAT's own img() markup (CCP image server), built on the server.
          name.innerHTML = r.icon;
          name.appendChild(document.createTextNode(r.name));
          tr.appendChild(name);
          tr.appendChild(cell(r.fmt.quantity));
          tr.appendChild(cell(r.fmt.station_sell));
          tr.appendChild(cell(r.fmt.jita_sell));
          tr.appendChild(cell(r.fmt.price, 'font-weight-bold'));
          var pct = r.vs_jita_pct;
          tr.appendChild(cell(pct === null ? null : (pct > 0 ? '+' : '') + r.fmt.vs_jita_pct + ' %',
            pct === null ? '' : (pct >= 0 ? 'text-success' : 'text-danger')));
          WAYS.forEach(function (way) { tr.appendChild(wayCell(r, way)); });
          var best = document.createElement('td');
          if (r.best) {
            var pill = document.createElement('span');
            pill.className = 'qs-pill qs-' + r.best;
            pill.textContent = t['way_' + r.best];
            best.appendChild(pill);
          }
          tr.appendChild(best);
          body.appendChild(tr);
        });

        // Tiles: net total of each way; the best single way is highlighted.
        var bestWay = null;
        WAYS.forEach(function (way) {
          if (bestWay === null || data.totals[way].raw > data.totals[bestWay].raw) bestWay = way;
        });
        $.each(data.totals, function (way, v) {
          var tile = $('[data-tile="' + way + '"]');
          tile.find('[data-tile-net]').text(v.net + ' ISK');
          tile.toggleClass('qs-win', way === bestWay && v.raw > 0);
        });

        showUnknown(data.unknown);
        showFees(data.fees, true);

        $('#qs-skills-char').text(data.seller.name ? '(' + data.seller.name + ')' : '');
        var list = $('#qs-skills-list').empty();
        data.skills.forEach(function (s) {
          list.append($('<div class="d-flex justify-content-between">')
            .append($('<span>').text(s.name)).append($('<b>').text(s.level)));
        });
        $('#qs-skills').removeClass('d-none');
        $('#qs-import').addClass('d-none');
      }

      function showUnknown(list) {
        var box = $('#qs-unknown');
        box.find('ul').empty();
        (list || []).forEach(function (line) { box.find('ul').append($('<li>').text(line)); });
        box.toggleClass('d-none', !(list && list.length));
      }

      // Copy as "name<TAB>price<TAB>quantity" lines. The clipboard API needs HTTPS; otherwise the text is shown.
      function copy(button, text) {
        var label = button.find('span').first();
        var original = label.text();
        function done() { label.text(t.copied); setTimeout(function () { label.text(original); }, 2000); }
        function fallback() {
          var area = $('#qs-import').val(text).removeClass('d-none');
          area[0].select();
          try { if (document.execCommand('copy')) { done(); return; } } catch (e) {}
          showError(t.copy_failed);
        }
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done, fallback);
        } else {
          fallback();
        }
      }
      $('#qs-copy').on('click', function () { if (lastQuote) copy($(this), lastQuote.import_text); });
      $('#qs-copy-all').on('click', function () { if (lastQuote) copy($(this), lastQuote.import_text_all); });

      $('#qs-check').on('click', function () { if (!busy) quote(false, false); });

      // Clears the pasted items and the result; market, character and fees stay.
      var emptyRow = $('#qs-rows').html();
      $('#qs-reset').on('click', function () {
        if (busy) return;
        lastQuote = null;
        $('#qs-items').val('').focus();
        $('#qs-rows').html(emptyRow);
        $('#qs-market-name').text(t.items);
        $('#qs-copy, #qs-copy-all').prop('disabled', true);
        $('#qs-copy-count').text('');
        $('[data-tile-net]').text('–');
        $('.qs-tile').removeClass('qs-win');
        $('#qs-error, #qs-denied, #qs-slow, #qs-unknown, #qs-import').addClass('d-none');
      });

      $('#qs-reset-fees').on('click', function () {
        var market = $('#qs-market').val();
        if (!market) return;
        $.post(urls.feesReset, { market: market }).done(function (d) { showFees(d.fees, d.saved); })
          .fail(function (xhr) { showError(errorMessage(xhr)); });
      });

      loadFees();
    });
  </script>
@endpush
