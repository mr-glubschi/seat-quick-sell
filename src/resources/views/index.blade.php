@extends('web::layouts.grids.12')

@section('title', trans('quicksell::global.title'))
@section('page_header', trans('quicksell::global.title'))
@section('page_description', trans('quicksell::global.description'))

@section('full')
  <div class="row">
    <div class="col-lg-4">
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
            <label for="qs-seller">{{ trans('quicksell::global.seller') }}</label>
            <select id="qs-seller" class="form-control">
              <option value="">{{ trans('quicksell::global.seller_auto') }}</option>
              @foreach($characters as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label for="qs-items">{{ trans('quicksell::global.items') }}</label>
            <textarea id="qs-items" class="form-control text-monospace" rows="12" spellcheck="false"></textarea>
            <small class="form-text text-muted">{{ trans('quicksell::global.items_help') }}</small>
          </div>
          <button id="qs-check" type="button" class="btn btn-primary btn-block">
            <i class="fas fa-search-dollar"></i> {{ trans('quicksell::global.check') }}
          </button>
          <div id="qs-progress" class="mt-2 small text-muted d-none"></div>
          <div id="qs-error" class="alert alert-danger mt-2 d-none"></div>
        </div>
      </div>

      <div id="qs-fees-card" class="card d-none">
        <div class="card-header">
          <h3 class="card-title">{{ trans('quicksell::global.fees') }}</h3>
          <span id="qs-fees-saved" class="badge badge-info float-right d-none">{{ trans('quicksell::global.fees_saved') }}</span>
        </div>
        <div class="card-body">
          <p class="small text-muted">{{ trans('quicksell::global.fees_help') }}</p>
          <div class="form-row">
            <div class="form-group col-4">
              <label class="small" for="qs-broker">{{ trans('quicksell::global.broker_pct') }}</label>
              <input id="qs-broker" type="number" step="0.01" min="0" max="100" class="form-control form-control-sm"
                     placeholder="{{ trans('quicksell::global.broker_unknown') }}">
            </div>
            <div class="form-group col-4">
              <label class="small" for="qs-scc">{{ trans('quicksell::global.scc_pct') }}</label>
              <input id="qs-scc" type="number" step="0.01" min="0" max="100" class="form-control form-control-sm">
            </div>
            <div class="form-group col-4">
              <label class="small" for="qs-safety">{{ trans('quicksell::global.safety_pct') }}</label>
              <input id="qs-safety" type="number" step="0.01" min="0" max="100" class="form-control form-control-sm">
            </div>
          </div>
          <div class="mb-2">
            <button id="qs-save-fees" type="button" class="btn btn-sm btn-outline-primary">{{ trans('quicksell::global.save_fees') }}</button>
            <button id="qs-reset-fees" type="button" class="btn btn-sm btn-outline-secondary">{{ trans('quicksell::global.reset_fees') }}</button>
          </div>
          <table class="table table-sm mb-0">
            <tbody>
              <tr><td>{{ trans('quicksell::global.gross') }}</td><td class="text-right" data-total="gross"></td></tr>
              <tr><td>{{ trans('quicksell::global.broker') }}</td><td class="text-right" data-total="broker"></td></tr>
              <tr><td>{{ trans('quicksell::global.scc') }}</td><td class="text-right" data-total="scc"></td></tr>
              <tr><td>{{ trans('quicksell::global.sales_tax') }} (<span id="qs-sales-tax"></span>%)</td><td class="text-right" data-total="sales_tax"></td></tr>
              <tr><td>{{ trans('quicksell::global.safety') }}</td><td class="text-right" data-total="safety"></td></tr>
              <tr class="font-weight-bold"><td>{{ trans('quicksell::global.net') }}</td><td class="text-right" data-total="net"></td></tr>
            </tbody>
          </table>
          <p class="small text-muted mt-2 mb-0">{{ trans('quicksell::global.per_order_min') }} <span id="qs-seller-name"></span></p>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div id="qs-result" class="card d-none">
        <div class="card-header">
          <h3 class="card-title" id="qs-market-name"></h3>
          <div class="card-tools">
            <button id="qs-copy" type="button" class="btn btn-sm btn-success">
              <i class="fas fa-copy"></i> <span>{{ trans('quicksell::global.copy') }}</span>
            </button>
          </div>
        </div>
        <div class="card-body p-0">
          <div id="qs-denied" class="alert alert-warning m-2 d-none">{{ trans('quicksell::global.denied') }}</div>
          <div id="qs-slow" class="alert alert-warning m-2 d-none">{{ trans('quicksell::global.fetch_slow') }}</div>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead>
                <tr>
                  <th></th>
                  <th>{{ trans('quicksell::global.col_item') }}</th>
                  <th class="text-right">{{ trans('quicksell::global.col_qty') }}</th>
                  <th class="text-right">{{ trans('quicksell::global.col_station_sell') }}</th>
                  <th class="text-right">{{ trans('quicksell::global.col_station_buy') }}</th>
                  <th class="text-right">{{ trans('quicksell::global.col_jita_sell') }}</th>
                  <th class="text-right">{{ trans('quicksell::global.col_jita_buy') }}</th>
                  <th class="text-right">{{ trans('quicksell::global.col_price') }}</th>
                  <th class="text-right">{{ trans('quicksell::global.col_vs_jita') }}</th>
                  <th class="text-right">{{ trans('quicksell::global.col_value') }}</th>
                </tr>
              </thead>
              <tbody id="qs-rows"></tbody>
            </table>
          </div>
          <div id="qs-unknown" class="alert alert-light m-2 small d-none">
            <strong>{{ trans('quicksell::global.unknown') }}</strong>
            <ul class="mb-0"></ul>
          </div>
          <div class="p-2 small text-muted">
            <strong>{{ trans('quicksell::global.legend') }}:</strong>
            <span class="badge badge-info">&nbsp;</span> {{ trans('quicksell::global.flag_no_offer') }}
            <br><i class="fas fa-user-tag text-primary"></i> {{ trans('quicksell::global.flag_own_order') }}
            <br><i class="fas fa-arrow-circle-up text-warning"></i> {{ trans('quicksell::global.flag_buy_above') }}
            <br><i class="fas fa-exclamation-triangle text-danger"></i> {{ trans('quicksell::global.flag_no_data') }}
          </div>
          <textarea id="qs-import" class="form-control text-monospace d-none" rows="4" readonly></textarea>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('javascript')
  <script>
    $(function () {
      var urls = {
        markets: @json(route('seatquicksell::markets')),
        quote: @json(route('seatquicksell::quote')),
        status: @json(route('seatquicksell::status')),
        fees: @json(route('seatquicksell::fees'))
      };
      var t = @json(trans('quicksell::global'));
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
      });

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

      function quote(noFetch, slow) {
        var market = $('#qs-market').val();
        if (!market) { showError(t.error_market); return; }
        $('#qs-error').addClass('d-none');
        setBusy(true);
        $.post(urls.quote, {
          market: market,
          items: $('#qs-items').val(),
          character_id: $('#qs-seller').val(),
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

      function render(data, slow) {
        lastQuote = data;
        $('#qs-result').removeClass('d-none');
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
          name.textContent = r.name;
          tr.appendChild(name);
          tr.appendChild(cell(r.fmt.quantity));
          tr.appendChild(cell(r.fmt.station_sell));
          tr.appendChild(cell(r.fmt.station_buy));
          tr.appendChild(cell(r.fmt.jita_sell));
          tr.appendChild(cell(r.fmt.jita_buy));
          tr.appendChild(cell(r.fmt.price, 'font-weight-bold'));
          var pct = r.vs_jita_pct;
          tr.appendChild(cell(pct === null ? null : (pct > 0 ? '+' : '') + r.fmt.vs_jita_pct + ' %',
            pct === null ? '' : (pct >= 0 ? 'text-success' : 'text-danger')));
          tr.appendChild(cell(r.fmt.value));
          body.appendChild(tr);
        });

        showUnknown(data.unknown);

        $('#qs-fees-card').removeClass('d-none');
        $('#qs-broker').val(data.fees.broker_pct === null ? '' : round(data.fees.broker_pct));
        $('#qs-scc').val(round(data.fees.scc_pct));
        $('#qs-safety').val(round(data.fees.safety_pct));
        $('#qs-sales-tax').text(round(data.fees.sales_tax_pct));
        $('#qs-fees-saved').toggleClass('d-none', !data.fees.saved);
        $('#qs-seller-name').text(data.seller.name ? '(' + data.seller.name + ')' : '');
        $.each(data.totals, function (key, v) { $('[data-total="' + key + '"]').text(v.fmt + ' ISK'); });

        $('#qs-import').val(data.import_text).addClass('d-none');
      }

      function round(v) { return Math.round(v * 1000) / 1000; }

      function showUnknown(list) {
        var box = $('#qs-unknown');
        box.find('ul').empty();
        (list || []).forEach(function (line) { box.find('ul').append($('<li>').text(line)); });
        box.toggleClass('d-none', !(list && list.length));
        if (list && list.length) $('#qs-result').removeClass('d-none');
      }

      // Copy as "name<TAB>price<TAB>quantity" lines. The clipboard API needs HTTPS; otherwise the text is shown.
      $('#qs-copy').on('click', function () {
        if (!lastQuote) return;
        var label = $(this).find('span');
        function done() { label.text(t.copied); setTimeout(function () { label.text(t.copy); }, 2000); }
        function fallback() {
          var area = $('#qs-import').removeClass('d-none');
          area[0].select();
          try { if (document.execCommand('copy')) { done(); return; } } catch (e) {}
          showError(t.copy_failed);
        }
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(lastQuote.import_text).then(done, fallback);
        } else {
          fallback();
        }
      });

      $('#qs-check').on('click', function () { if (!busy) quote(false, false); });

      function postFees(payload) {
        payload.market = $('#qs-market').val();
        $.post(urls.fees, payload).done(function () { quote(true, false); })
          .fail(function (xhr) { showError(errorMessage(xhr)); });
      }

      $('#qs-save-fees').on('click', function () {
        var v = function (id) { var x = $(id).val(); return x === '' ? null : x; };
        postFees({ broker_pct: v('#qs-broker'), scc_pct: v('#qs-scc'), safety_pct: v('#qs-safety') });
      });
      $('#qs-reset-fees').on('click', function () { postFees({ reset: 1 }); });
    });
  </script>
@endpush
