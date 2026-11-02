<?php
// includes/footer.php - closes .page-content, footer note, interactive behaviour (vanilla JS), </body></html>
$date = date('Y-m-d');
?>
  </div><!-- /.page-content -->

  <p class="text-center text-muted footer-note">
    Project/Task Management System &copy; <?php echo e($date); ?> &middot; made with <i class="fa-solid fa-heart"></i>
  </p>

  <button type="button" class="plain to-top" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></button>
  <canvas id="confetti" aria-hidden="true"></canvas>

  <script>
  (function () {
    'use strict';
    var doc = document, root = doc.documentElement;
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    function $(s, c) { return (c || doc).querySelector(s); }
    function $$(s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); }
    function slug(s) { return String(s).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
    function keep(store, k, v) {
      try { if (v === undefined) return window[store].getItem(k); if (v === null) window[store].removeItem(k); else window[store].setItem(k, v); } catch (e) {}
      return null;
    }

    /* ---------- 1. Light / dark toggle ---------- */
    var themeBtn = $('.theme-toggle');
    function paintThemeIcon() {
      if (!themeBtn) return;
      var dark = root.getAttribute('data-bs-theme') === 'dark';
      themeBtn.innerHTML = '<i class="fa-solid ' + (dark ? 'fa-sun' : 'fa-moon') + '"></i>';
    }
    if (themeBtn) {
      paintThemeIcon();
      themeBtn.addEventListener('click', function () {
        var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        keep('localStorage', 'tm-theme', next);
        paintThemeIcon();
      });
    }

    /* ---------- 2. Flash messages: icon, close button, auto-dismiss ---------- */
    function dismiss(a) {
      a.classList.add('alert-out');
      setTimeout(function () { a.remove(); }, 350);
    }
    $$('.page-content > .alert').forEach(function (a) {
      var icon = /alert-success/.test(a.className) ? 'fa-circle-check'
        : /alert-danger/.test(a.className) ? 'fa-circle-exclamation'
        : /alert-warning/.test(a.className) ? 'fa-triangle-exclamation' : 'fa-circle-info';
      a.insertAdjacentHTML('afterbegin', '<i class="fa-solid ' + icon + ' alert-icon"></i>');
      var x = doc.createElement('button');
      x.type = 'button'; x.className = 'plain alert-close'; x.setAttribute('aria-label', 'Dismiss'); x.innerHTML = '&times;';
      x.addEventListener('click', function () { dismiss(a); });
      a.appendChild(x);
      if (/alert-(success|info)/.test(a.className)) {
        a.classList.add('auto');
        a.addEventListener('animationend', function (ev) { if (ev.animationName === 'alert-timer') dismiss(a); });
      }
    });

    /* ---------- 3. Show / hide password ---------- */
    $$('input[type=password]').forEach(function (inp) {
      if (inp.parentNode.classList.contains('input-group')) return;
      var wrap = doc.createElement('div');
      wrap.className = 'pw-wrap';
      inp.parentNode.insertBefore(wrap, inp);
      wrap.appendChild(inp);
      var b = doc.createElement('button');
      b.type = 'button'; b.className = 'plain pw-toggle'; b.setAttribute('aria-label', 'Show password');
      b.innerHTML = '<i class="fa-solid fa-eye"></i>';
      b.addEventListener('click', function () {
        var show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        b.innerHTML = '<i class="fa-solid ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
        b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
      wrap.appendChild(b);
    });

    /* ---------- 4. Tables: search, status chips, sorting, clickable rows ---------- */
    function cellValue(td) {
      if (!td) return '';
      var sel = td.querySelector('select');
      if (sel) return sel.value;
      var out = '';
      td.childNodes.forEach(function (n) {
        if (n.nodeType === 3) out += n.textContent;
        else if (n.nodeType === 1 && !n.classList.contains('due-chip')) out += n.textContent;
      });
      return out.replace(/\s+/g, ' ').trim();
    }

    $$('.table-card table').forEach(function (table) {
      var tbody = table.tBodies[0];
      if (!tbody || tbody.rows.length === 0) return;
      var heads = $$('thead th', table);
      if (!heads.length || tbody.rows[0].cells.length !== heads.length) return;

      function rows() { return Array.prototype.slice.call(tbody.rows); }
      function headIdx(re) { return heads.findIndex(function (th) { return re.test(th.textContent.trim()); }); }
      var statusIdx = headIdx(/^status$/i);
      var searchCols = heads.map(function (th, i) { return /^actions?$/i.test(th.textContent.trim()) ? -1 : i; })
        .filter(function (i) { return i > -1; });

      function paintRow(tr) {
        if (statusIdx > -1) tr.setAttribute('data-status', slug(cellValue(tr.cells[statusIdx])));
      }
      rows().forEach(function (tr) {
        paintRow(tr);
        // click anywhere on a row to open its "View" page
        var view = tr.querySelector('a[href*="view.php"]');
        if (view) {
          tr.classList.add('clickable');
          tr.title = 'Click to open';
          tr.addEventListener('click', function (e) {
            if (e.target.closest('a, button, select, input, textarea, form, label')) return;
            window.location.href = view.href;
          });
        }
      });

      /* toolbar */
      var activeChip = 'all', query = '', input = null, countEl = null, chips = [], empty = null;
      if (rows().length >= 3) {
        var tools = doc.createElement('div');
        tools.className = 'table-tools';
        tools.innerHTML =
          '<div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>' +
          '<input type="search" class="form-control" placeholder="Search…  (press /)" aria-label="Search table"></div>' +
          '<div class="chip-group"></div><span class="result-count"></span>';
        var anchor = table.closest('.table-responsive') || table;
        anchor.parentNode.insertBefore(tools, anchor);
        input = $('input', tools);
        countEl = $('.result-count', tools);

        if (statusIdx > -1) {
          var seen = [];
          rows().forEach(function (tr) {
            var v = cellValue(tr.cells[statusIdx]);
            if (v && seen.indexOf(v) === -1) seen.push(v);
          });
          if (seen.length > 1) {
            var group = $('.chip-group', tools);
            ['All'].concat(seen).forEach(function (label) {
              var c = doc.createElement('button');
              c.type = 'button';
              c.className = 'plain chip' + (label === 'All' ? ' active' : '');
              c.setAttribute('data-value', label === 'All' ? 'all' : slug(label));
              c.setAttribute('data-label', label);
              c.innerHTML = label + '<span class="chip-n"></span>';
              c.addEventListener('click', function () {
                activeChip = c.getAttribute('data-value');
                chips.forEach(function (x) { x.classList.toggle('active', x === c); });
                apply();
              });
              group.appendChild(c);
              chips.push(c);
            });
          }
        }

        empty = doc.createElement('div');
        empty.className = 'empty-state';
        empty.hidden = true;
        empty.innerHTML = '<i class="fa-regular fa-face-frown-open"></i>Nothing matches your search.';
        anchor.parentNode.insertBefore(empty, anchor.nextSibling);

        input.addEventListener('input', function () { query = input.value.trim().toLowerCase(); apply(); });
        input.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') { input.value = ''; query = ''; apply(); input.blur(); }
        });
      }

      function apply() {
        var all = rows(), shown = 0;
        var counts = { all: all.length };
        all.forEach(function (tr) {
          var st = statusIdx > -1 ? slug(cellValue(tr.cells[statusIdx])) : '';
          counts[st] = (counts[st] || 0) + 1;
          var text = searchCols.map(function (i) { return cellValue(tr.cells[i]); }).join(' ').toLowerCase();
          var show = (!query || text.indexOf(query) > -1) && (activeChip === 'all' || st === activeChip);
          tr.hidden = !show;
          if (show) {
            tr.classList.remove('even', 'odd');
            tr.classList.add(shown++ % 2 ? 'odd' : 'even');
            tr.classList.add('in');
          }
        });
        chips.forEach(function (c) {
          $('.chip-n', c).textContent = counts[c.getAttribute('data-value')] || 0;
        });
        if (countEl) countEl.textContent = shown + ' of ' + all.length + ' shown';
        if (empty) empty.hidden = shown !== 0;
      }
      apply();

      // keep chips + row accents in sync when a status dropdown changes
      table.addEventListener('change', function (e) {
        if (e.target.matches('select')) { paintRow(e.target.closest('tr')); apply(); }
      });

      /* sorting */
      heads.forEach(function (th, i) {
        if (/^(actions?)?$/i.test(th.textContent.trim())) return;
        th.classList.add('sortable');
        th.tabIndex = 0;
        function go() {
          var dir = th.classList.contains('asc') ? 'desc' : 'asc';
          heads.forEach(function (h) { h.classList.remove('asc', 'desc'); });
          th.classList.add(dir);
          var num = /^-?\d+(\.\d+)?$/;
          rows().sort(function (a, b) {
            var x = cellValue(a.cells[i]), y = cellValue(b.cells[i]);
            var r = (num.test(x) && num.test(y))
              ? parseFloat(x) - parseFloat(y)
              : x.localeCompare(y, undefined, { numeric: true, sensitivity: 'base' });
            return dir === 'asc' ? r : -r;
          }).forEach(function (tr) { tbody.appendChild(tr); });
          apply();
        }
        th.addEventListener('click', go);
        th.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
        });
      });
    });

    // "/" jumps to the search box
    doc.addEventListener('keydown', function (e) {
      if (e.key !== '/' || /^(input|textarea|select)$/i.test(doc.activeElement.tagName)) return;
      var s = $('.table-tools input');
      if (s) { e.preventDefault(); s.focus(); }
    });

    /* ---------- 5. Due-date chips (Overdue / Due today / in N days) ---------- */
    var DATE_RE = /^\s*\d{4}-\d{2}-\d{2}\s*$/;
    function dayDiff(str) {
      var p = str.trim().split('-');
      var d = new Date(+p[0], +p[1] - 1, +p[2]);
      var t = new Date(); t.setHours(0, 0, 0, 0);
      return Math.round((d - t) / 86400000);
    }
    function addChip(container) {
      container.childNodes.forEach(function (n) {
        if (n.nodeType !== 3 || !DATE_RE.test(n.textContent)) return;
        var d = dayDiff(n.textContent), s = doc.createElement('span'), cls, txt;
        if (d < 0)       { cls = 'due-over'; txt = 'Overdue ' + (-d) + 'd'; }
        else if (d === 0) { cls = 'due-soon'; txt = 'Due today'; }
        else if (d === 1) { cls = 'due-soon'; txt = 'Due tomorrow'; }
        else if (d <= 7)  { cls = 'due-soon'; txt = 'in ' + d + ' days'; }
        else              { cls = 'due-ok'; txt = 'in ' + d + ' days'; }
        s.className = 'due-chip ' + cls;
        s.textContent = txt;
        n.parentNode.insertBefore(s, n.nextSibling);
      });
    }
    $$('.table-card table').forEach(function (table) {
      var heads = $$('thead th', table);
      var statusIdx = heads.findIndex(function (th) { return /^status$/i.test(th.textContent.trim()); });
      heads.forEach(function (th, i) {
        if (!/deadline|due/i.test(th.textContent)) return;
        $$('tbody tr', table).forEach(function (tr) {
          var done = statusIdx > -1 && slug(cellValue(tr.cells[statusIdx])) === 'completed';
          if (!done && tr.cells[i]) addChip(tr.cells[i]);
        });
      });
    });
    var pageStatus = '';
    $$('.row.mb-3 > [class*="col-"]').forEach(function (col) {
      var s = $('strong', col);
      if (s && /^status$/i.test(s.textContent.trim())) {
        var b = $('.badge', col);
        if (b) pageStatus = slug(b.textContent);
      }
    });
    $$('.row.mb-3 > [class*="col-"]').forEach(function (col) {
      var s = $('strong', col);
      if (s && /^(deadline|due date)$/i.test(s.textContent.trim()) && pageStatus !== 'completed') addChip(col);
    });

    /* ---------- 6. Stat tiles: count-up, progress bar, confetti easter egg ---------- */
    var tiles = $$('.stat-tile');
    tiles.forEach(function (tile) {
      var n = $('.stat-number', tile);
      var target = parseInt(n.textContent, 10) || 0;
      tile._real = target;                       // remember the true number
      if (reduce || target === 0) return;
      n.textContent = '0';
      var start = null;
      (function step(t) {
        if (!start) start = t;
        var p = Math.min((t - start) / 900, 1);
        n.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(step);
      })(performance.now());
    });
    if (tiles.length) {
      var total = 0, done = 0;
      tiles.forEach(function (t) {
        total += t._real;
        if (t.classList.contains('stat-done')) done = t._real;
      });
      var row = tiles[0].closest('.row');
      if (row && total > 0) {
        var pct = Math.round(done / total * 100);
        row.insertAdjacentHTML('afterend',
          '<div class="progress-wrap"><div class="progress-head"><span><i class="fa-solid fa-flag-checkered"></i> Project progress</span><span>' +
          pct + '% complete</span></div><div class="progress-track"><div class="progress-fill"></div></div></div>');
        var fill = $('.progress-fill', row.parentNode);
        setTimeout(function () { fill.style.width = pct + '%'; }, 250);
      }
    }
    $$('.stat-done').forEach(function (t) { t.title = 'Click for a surprise'; t.addEventListener('click', confetti); });

    /* ---------- 7. Status dropdowns: colour, "unsaved" glow, confetti on Completed ---------- */
    function paintSelect(sel) {
      sel.className = sel.className.replace(/\bst-\S+/g, '').trim();
      sel.classList.add('st-' + slug(sel.value));
    }
    $$('select[name=status]').forEach(function (sel) {
      var orig = sel.value;
      paintSelect(sel);
      sel.addEventListener('change', function () {
        paintSelect(sel);
        var btn = sel.form && $('button[type=submit], button:not([type])', sel.form);
        if (btn) btn.classList.toggle('is-dirty', sel.value !== orig);
      });
    });
    doc.addEventListener('submit', function (e) {
      var f = e.target, sel = $('select[name=status]', f);
      if (sel && sel.value === 'Completed' && /update_status/.test(f.getAttribute('action') || '')) {
        keep('sessionStorage', 'tm-confetti', '1');
      }
      // loading spinner on the submit button (skipped if a confirm() was cancelled)
      setTimeout(function () {
        if (e.defaultPrevented) return;
        var b = $('button[type=submit], button:not([type])', f);
        if (b && !b.classList.contains('plain')) b.classList.add('is-loading');
      }, 0);
    });
    window.addEventListener('pageshow', function () {
      $$('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); });
    });
    if (keep('sessionStorage', 'tm-confetti')) {
      keep('sessionStorage', 'tm-confetti', null);
      if ($('.page-content > .alert-success')) setTimeout(confetti, 350);
    }

    /* ---------- 8. Button ripple + spotlight on tiles ---------- */
    doc.addEventListener('pointerdown', function (e) {
      var b = e.target.closest('button:not(.plain):not(.navbar-toggler), .button');
      if (!b || b.disabled || reduce) return;
      var r = b.getBoundingClientRect(), s = Math.max(r.width, r.height);
      var sp = doc.createElement('span');
      sp.className = 'ripple';
      sp.style.width = sp.style.height = s + 'px';
      sp.style.left = (e.clientX - r.left - s / 2) + 'px';
      sp.style.top = (e.clientY - r.top - s / 2) + 'px';
      b.appendChild(sp);
      setTimeout(function () { sp.remove(); }, 650);
    });
    $$('.stat-tile, .table-card .row.mb-3 > [class*="col-"]').forEach(function (el) { el.classList.add('spot'); });
    doc.addEventListener('pointermove', function (e) {
      var t = e.target.closest && e.target.closest('.spot');
      if (!t) return;
      var r = t.getBoundingClientRect();
      t.style.setProperty('--mx', (e.clientX - r.left) + 'px');
      t.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });

    /* ---------- 9. Scroll-reveal ---------- */
    var revealEls = $$('.table-card tbody tr, .stat-tile, .progress-wrap, .table-card .row.mb-3 > [class*="col-"]:not(:has(.stat-tile))');
    revealEls.forEach(function (el) { el.classList.add('reveal'); });
    if ('IntersectionObserver' in window && !reduce) {
      var io = new IntersectionObserver(function (entries) {
        var j = 0;
        entries.forEach(function (en) {
          if (!en.isIntersecting) return;
          en.target.style.setProperty('--d', (j++ * 60) + 'ms');
          en.target.classList.add('in');
          io.unobserve(en.target);
        });
      }, { threshold: 0.08 });
      revealEls.forEach(function (el) { io.observe(el); });
    } else {
      revealEls.forEach(function (el) { el.classList.add('in'); });
    }

    /* ---------- 10. Back-to-top ---------- */
    var topBtn = $('.to-top');
    if (topBtn) {
      window.addEventListener('scroll', function () { topBtn.classList.toggle('show', window.scrollY > 400); }, { passive: true });
      topBtn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });
    }

    /* ---------- 11. Confetti ---------- */
    function confetti() {
      var cv = $('#confetti');
      if (reduce || !cv) return;
      var ctx = cv.getContext('2d');
      cv.width = window.innerWidth; cv.height = window.innerHeight; cv.style.display = 'block';
      var colors = ['#7c3aed', '#ec4899', '#f97316', '#f59e0b', '#14b8a6', '#0ea5e9', '#22c55e'];
      var parts = [];
      for (var i = 0; i < 150; i++) {
        parts.push({
          x: window.innerWidth / 2 + (Math.random() - 0.5) * 240, y: window.innerHeight * 0.4,
          vx: (Math.random() - 0.5) * 16, vy: Math.random() * -14 - 3, g: 0.35 + Math.random() * 0.2,
          s: 6 + Math.random() * 7, r: Math.random() * 6.28, vr: (Math.random() - 0.5) * 0.4, c: colors[i % colors.length]
        });
      }
      var frames = 0;
      (function tick() {
        ctx.clearRect(0, 0, cv.width, cv.height);
        parts.forEach(function (p) {
          p.vy += p.g; p.x += p.vx; p.y += p.vy; p.vx *= 0.99; p.r += p.vr;
          ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r);
          ctx.fillStyle = p.c; ctx.fillRect(-p.s / 2, -p.s / 3, p.s, p.s * 0.6);
          ctx.restore();
        });
        if (++frames < 170) requestAnimationFrame(tick);
        else { ctx.clearRect(0, 0, cv.width, cv.height); cv.style.display = 'none'; }
      })();
    }
  })();
  </script>
</body>

</html>