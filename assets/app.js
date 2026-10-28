/**
 * AgriMarket – progressive enhancements.
 * Every page works without JavaScript; this file adds convenience only.
 */
(function () {
  'use strict';

  var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- Mobile navigation ---------- */
  var menuToggle = $('#menu-toggle');
  var mobileMenu = $('#mobile-menu');
  if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', function () {
      var open = mobileMenu.classList.toggle('hidden') === false;
      menuToggle.setAttribute('aria-expanded', String(open));
    });
  }

  /* ---------- Close the account menu when clicking elsewhere ---------- */
  document.addEventListener('click', function (event) {
    $$('details.user-menu[open]').forEach(function (menu) {
      if (!menu.contains(event.target)) { menu.removeAttribute('open'); }
    });
  });

  /* ---------- Auto-dismiss flash messages ---------- */
  $$('.flash').forEach(function (el) {
    setTimeout(function () {
      el.classList.add('hide');
      setTimeout(function () { el.remove(); }, 400);
    }, 6000);
  });

  /* ---------- Confirm destructive actions ---------- */
  document.addEventListener('submit', function (event) {
    var message = event.target.getAttribute('data-confirm');
    if (message && !window.confirm(message)) { event.preventDefault(); }
  });

  /* ---------- Quantity steppers ---------- */
  $$('[data-stepper]').forEach(function (stepper) {
    var input = $('input', stepper);
    var max = parseInt(input.getAttribute('max'), 10) || 999;
    $$('[data-step]', stepper).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = (parseInt(input.value, 10) || 1) + parseInt(btn.getAttribute('data-step'), 10);
        input.value = Math.min(max, Math.max(1, next));
      });
    });
  });

  /* ---------- Register: show farm fields for farmers ---------- */
  var farmerFields = $('#farmer-fields');
  if (farmerFields) {
    var syncRole = function () {
      var selected = $('input[name="role"]:checked');
      var isFarmer = selected && selected.value === 'farmer';
      farmerFields.classList.toggle('hidden', !isFarmer);
      $$('input', farmerFields).forEach(function (input) { input.required = !!isFarmer; });
    };
    $$('input[name="role"]').forEach(function (radio) { radio.addEventListener('change', syncRole); });
    syncRole();
  }

  /* ---------- Catalogue: live filtering via api/produce.php ---------- */
  var form = $('#filter-form');
  var grid = $('#catalogue-grid');
  if (form && grid && window.fetch) {
    var count = $('#result-count');
    var pager = $('#pagination');
    var timer = null;
    var controller = null;

    var money = function (n) {
      n = Number(n);
      return 'KES ' + n.toLocaleString('en-KE', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
    };

    var cardHtml = function (p) {
      return '<article class="product-card bg-brand-cream rounded-xl border border-brand-cardborder shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-md transition-shadow">' +
        '<div>' +
          '<a href="' + escapeHtml(p.url) + '" class="block h-44 bg-gray-200 relative overflow-hidden">' +
            '<img src="' + escapeHtml(p.image_url) + '" alt="' + escapeHtml(p.title) + '" loading="lazy" class="w-full h-full object-cover">' +
            '<span class="absolute top-3 left-3 bg-white/90 text-brand-green text-xs font-semibold px-2.5 py-1 rounded-full backdrop-blur-sm">' + escapeHtml(p.location) + '</span>' +
            (p.in_stock ? '' : '<span class="absolute top-3 right-3 bg-red-600 text-white text-xs font-semibold px-2.5 py-1 rounded-full">Out of stock</span>') +
          '</a>' +
          '<div class="p-4">' +
            '<div class="text-xs text-brand-orange font-semibold uppercase tracking-wider mb-1">' + escapeHtml(p.category) + '</div>' +
            '<h3 class="font-bold text-lg text-brand-green mb-1 line-clamp-1">' + escapeHtml(p.title) + '</h3>' +
            '<p class="text-xs text-gray-500 mb-3">' + escapeHtml(p.farm_name) + '</p>' +
            '<div class="text-base font-bold text-brand-green">' + money(p.price) +
              ' <span class="text-xs font-normal text-gray-600">/ ' + escapeHtml(p.unit) + '</span></div>' +
          '</div>' +
        '</div>' +
        '<div class="p-4 pt-0"><a href="' + escapeHtml(p.url) + '" class="btn btn-outline w-full">View details</a></div>' +
      '</article>';
    };

    var emptyHtml = '<div class="col-span-full text-center py-12 bg-white rounded-xl border border-brand-cardborder">' +
      '<i class="fa-solid fa-seedling text-4xl text-gray-400 mb-3"></i>' +
      '<p class="text-gray-600 font-medium">No produce found matching your criteria.</p></div>';

    var pagerHtml = function (page, pages) {
      if (pages <= 1) { return ''; }
      var base = 'px-3.5 py-2 rounded-md border border-brand-cardborder text-sm font-medium';
      var link = function (n, label) {
        return '<a data-page="' + n + '" href="catalogue.php?page=' + n + '" class="' + base + ' bg-white text-gray-700 hover:bg-gray-50">' + label + '</a>';
      };
      var html = '<nav class="flex flex-wrap items-center justify-center gap-2" aria-label="Pagination">';
      html += page > 1 ? link(page - 1, 'Previous') : '<span class="' + base + ' bg-white text-gray-400 opacity-60">Previous</span>';
      for (var i = 1; i <= pages; i++) {
        html += i === page
          ? '<span aria-current="page" class="' + base + ' bg-brand-green text-white font-semibold">' + i + '</span>'
          : link(i, i);
      }
      html += page < pages ? link(page + 1, 'Next') : '<span class="' + base + ' bg-white text-gray-400 opacity-60">Next</span>';
      return html + '</nav>';
    };

    var load = function (page) {
      var params = new URLSearchParams(new FormData(form));
      Array.from(params.keys()).forEach(function (key) { if (!params.get(key)) { params.delete(key); } });
      if (page > 1) { params.set('page', page); }

      if (controller) { controller.abort(); }
      controller = new AbortController();
      grid.setAttribute('aria-busy', 'true');
      grid.style.opacity = '0.5';

      fetch('api/produce.php?' + params.toString(), { signal: controller.signal, headers: { Accept: 'application/json' } })
        .then(function (res) { if (!res.ok) { throw new Error('Request failed'); } return res.json(); })
        .then(function (data) {
          grid.innerHTML = data.items.length ? data.items.map(cardHtml).join('') : emptyHtml;
          if (count) { count.textContent = data.total + (data.total === 1 ? ' listing found' : ' listings found'); }
          if (pager) { pager.innerHTML = pagerHtml(data.page, data.pages); }
          var qs = params.toString();
          window.history.replaceState(null, '', 'catalogue.php' + (qs ? '?' + qs : ''));
        })
        .catch(function (err) {
          if (err.name !== 'AbortError') { form.submit(); }   // graceful fallback
        })
        .finally(function () { grid.removeAttribute('aria-busy'); grid.style.opacity = ''; });
    };

    form.addEventListener('submit', function (event) { event.preventDefault(); load(1); });
    $$('select', form).forEach(function (select) { select.addEventListener('change', function () { load(1); }); });
    var search = $('input[name="q"]', form);
    if (search) {
      search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { load(1); }, 300); });
    }
    if (pager) {
      pager.addEventListener('click', function (event) {
        var a = event.target.closest('a');
        if (!a) { return; }
        event.preventDefault();
        var page = parseInt(a.getAttribute('data-page') || new URL(a.href, window.location.href).searchParams.get('page'), 10) || 1;
        load(page);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }
  }
})();
