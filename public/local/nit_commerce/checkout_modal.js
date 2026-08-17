/*
 * NIT shared checkout modal (courses + subscriptions).
 *
 * A page mints its config + strings, then calls NitCheckout.open({...}) from a Buy button. The modal
 * previews the price (auto offer + optional coupon) via /local/nit_commerce/api.php?function=preview_discount
 * and, on Proceed, calls the caller's proceed(couponCode) to start the real Kashier checkout.
 *
 * Usage (from a server page):
 *   window.NIT_CO = { wwwroot: M.cfg.wwwroot, sesskey: M.cfg.sesskey,
 *                     commerce: '/local/nit_commerce/api.php', str: {...} };
 *   NitCheckout.open({ itemType:'course', itemId:10, name:'...', subtitle:'',
 *                      proceed: function(code){ location = checkoutUrl + '&coupon_code=' + code; } });
 */
(function (w) {
  'use strict';

  var cfg = null, modal = null, els = {}, current = null;

  function S(k) { return (cfg && cfg.str && cfg.str[k] != null) ? cfg.str[k] : k; }
  function money(n) { return (Math.round(Number(n) * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function cur() { return S('co_currency'); }

  function el(tag, style, text) {
    var e = document.createElement(tag);
    if (style) { e.style.cssText = style; }
    if (text != null) { e.textContent = text; }
    return e;
  }

  var C = {
    bg: 'var(--nit-darkbackground, #0a1628)',
    surface: 'var(--nit-darksurface, #0f1e33)',
    ink: 'var(--nit-darktextprimary, #ffffff)',
    muted: 'var(--nit-darktextsecondary, #8a9ab5)',
    // Split accent: `gold` is the NON-TEXT accent (card border, crown emblem,
    // button border, the proceed-button gradient) -> Accent role. `goldtext` is
    // the TEXT accent (the final price, the "apply" label) -> Accent Text role.
    gold: 'var(--nit-brand-accent, #e8b84b)',
    goldtext: 'var(--nit-brand-accenttext, #e8b84b)',
    golddark: 'var(--nit-accentgolddark, #c9922a)',
    teal: 'var(--nit-accentteal, #00a99d)',
    line: 'color-mix(in srgb, var(--nit-darktextprimary, #ffffff) 10%, transparent)'
  };

  function build() {
    if (modal) { return; }
    modal = el('div', 'display:none; position:fixed; inset:0; background:rgba(3,8,20,.72); z-index:99999; align-items:center; justify-content:center; padding:16px;');

    var card = el('div', 'width:100%; max-width:460px; background:' + C.surface + '; border:1px solid color-mix(in srgb, ' + C.gold + ' 22%, transparent); border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.5); overflow:hidden;');

    var head = el('div', 'padding:22px 24px 0;');
    var h3 = el('h3', 'display:flex; align-items:center; gap:10px; font-size:20px; font-weight:800; color:' + C.ink + '; margin:0;');
    h3.appendChild(el('span', 'color:' + C.gold + ';', '♛'));
    h3.appendChild(document.createTextNode(' ' + S('co_title')));
    head.appendChild(h3);
    head.appendChild(el('p', 'color:' + C.muted + '; font-size:14px; line-height:1.6; margin:10px 0 0;', S('co_intro')));
    card.appendChild(head);

    var box = el('div', 'margin:18px 24px; padding:16px; border:1px solid ' + C.line + '; border-radius:12px; background:' + C.bg + ';');
    els.name = el('div', 'font-size:15px; font-weight:700; color:' + C.ink + '; margin-bottom:6px;', '—');
    box.appendChild(els.name);
    els.subtitle = el('div', 'font-size:12px; color:' + C.muted + '; margin-bottom:12px; display:none;');
    box.appendChild(els.subtitle);

    box.appendChild(row(S('co_total'), (els.original = el('b', 'color:' + C.ink + ';', '—'))));
    els.offerRow = row(S('co_offer'), (els.offer = el('b', 'color:' + C.teal + ';', '—')));
    els.offerRow.style.display = 'none';
    box.appendChild(els.offerRow);

    // Coupon input + apply.
    var cRow = el('div', 'display:flex; align-items:center; gap:8px; margin:12px 0;');
    cRow.appendChild(el('span', 'font-size:14px; color:' + C.muted + '; flex:0 0 auto;', S('co_coupon')));
    els.coupon = el('input', 'flex:1; min-width:0; background:' + C.surface + '; border:1px solid color-mix(in srgb, ' + C.ink + ' 15%, transparent); border-radius:8px; color:' + C.ink + '; padding:7px 10px; font-size:14px;');
    els.coupon.type = 'text'; els.coupon.autocomplete = 'off';
    cRow.appendChild(els.coupon);
    els.apply = el('button', 'flex:0 0 auto; background:transparent; border:1px solid ' + C.gold + '; color:' + C.goldtext + '; border-radius:8px; padding:7px 14px; font-weight:700; cursor:pointer; font-size:14px;', S('co_apply'));
    els.apply.type = 'button';
    cRow.appendChild(els.apply);
    box.appendChild(cRow);

    els.couponErr = el('div', 'display:none; color:#ff6b6b; font-size:12px; margin:-6px 0 10px;', ' ');
    box.appendChild(els.couponErr);

    box.appendChild(row(S('co_discount'), (els.discount = el('b', 'color:' + C.teal + ';', '0.00 ' + cur()))));

    var totalRow = el('div', 'border-top:1px solid ' + C.line + '; padding-top:12px; display:flex; justify-content:space-between; font-size:16px; font-weight:800;');
    totalRow.appendChild(el('span', 'color:' + C.ink + ';', S('co_total')));
    els.final = el('b', 'color:' + C.goldtext + ';', '—');
    totalRow.appendChild(els.final);
    box.appendChild(totalRow);
    card.appendChild(box);

    var secure = el('div', 'padding:0 24px; color:' + C.muted + '; font-size:13px; display:flex; align-items:center; gap:6px;');
    secure.appendChild(el('span', '', '🔒'));
    secure.appendChild(document.createTextNode(' ' + S('co_secure')));
    card.appendChild(secure);

    els.error = el('div', 'display:none; margin:12px 24px 0; color:#ff6b6b; font-size:13px;', ' ');
    card.appendChild(els.error);

    var actions = el('div', 'display:flex; justify-content:flex-end; gap:10px; padding:18px 24px 22px;');
    els.cancel = el('button', 'background:transparent; border:1px solid color-mix(in srgb, ' + C.ink + ' 18%, transparent); color:' + C.muted + '; border-radius:8px; padding:9px 18px; font-weight:700; cursor:pointer;', S('co_cancel'));
    els.cancel.type = 'button';
    els.proceed = el('button', 'background:linear-gradient(135deg, ' + C.golddark + ', ' + C.gold + '); border:0; color:' + C.bg + '; border-radius:8px; padding:9px 20px; font-weight:800; cursor:pointer;', S('co_proceed'));
    els.proceed.type = 'button';
    actions.appendChild(els.cancel);
    actions.appendChild(els.proceed);
    card.appendChild(actions);

    modal.appendChild(card);
    document.body.appendChild(modal);

    // Wiring.
    els.cancel.addEventListener('click', close);
    modal.addEventListener('click', function (ev) { if (ev.target === modal) { close(); } });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && modal.style.display !== 'none') { close(); } });
    els.apply.addEventListener('click', function () { preview(els.coupon.value.trim()); });
    els.proceed.addEventListener('click', function () {
      if (!current) { return; }
      els.proceed.disabled = true;
      try { current.proceed(els.coupon.value.trim()); }
      catch (e) { els.proceed.disabled = false; els.error.textContent = String(e && e.message || e); els.error.style.display = ''; }
    });
  }

  function row(label, valueEl) {
    var r = el('div', 'display:flex; justify-content:space-between; font-size:14px; color:' + C.muted + '; margin-bottom:10px;');
    r.appendChild(el('span', '', label));
    r.appendChild(valueEl);
    return r;
  }

  function close() { if (modal) { modal.style.display = 'none'; } current = null; }

  // Fetch a fresh price preview (auto offer + optional coupon) and paint the modal.
  function preview(code) {
    if (!current) { return; }
    var url = cfg.wwwroot + cfg.commerce + '?function=preview_discount&item_type=' + encodeURIComponent(current.itemType) +
      '&item_id=' + encodeURIComponent(current.itemId) + '&coupon_code=' + encodeURIComponent(code || '') +
      '&sesskey=' + encodeURIComponent(cfg.sesskey);
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') { throw new Error('preview failed'); }
        var d = res.data || {};
        els.original.textContent = money(d.original != null ? d.original : (current.price || 0)) + ' ' + cur();
        els.final.textContent = money(d.final != null ? d.final : (current.price || 0)) + ' ' + cur();
        els.discount.textContent = money(d.discount || 0) + ' ' + cur();
        // Offer line (auto-applied), with its name if present.
        var offerDisc = Number(d.offer_discount || 0);
        if (offerDisc > 0) {
          var oname = (d.offers && d.offers[0] && d.offers[0].name) ? d.offers[0].name : (d.offer_name || '');
          els.offer.textContent = '-' + money(offerDisc) + ' ' + cur() + (oname ? ('  (' + oname + ')') : '');
          els.offerRow.style.display = '';
        } else {
          els.offerRow.style.display = 'none';
        }
        if (d.coupon_error) { els.couponErr.textContent = d.coupon_error; els.couponErr.style.display = ''; }
        else { els.couponErr.style.display = 'none'; }
      })
      .catch(function () { els.couponErr.textContent = S('co_coupon_failed'); els.couponErr.style.display = ''; });
  }

  var NitCheckout = {
    init: function (config) { cfg = config; build(); },
    open: function (item) {
      if (!cfg) { return; }
      build();
      current = item;
      els.name.textContent = item.name || '—';
      if (item.subtitle) { els.subtitle.textContent = item.subtitle; els.subtitle.style.display = ''; }
      else { els.subtitle.style.display = 'none'; }
      els.coupon.value = '';
      els.couponErr.style.display = 'none';
      els.error.style.display = 'none';
      els.proceed.disabled = false;
      var base = money(item.price || 0) + ' ' + cur();
      els.original.textContent = base; els.final.textContent = base; els.discount.textContent = '0.00 ' + cur();
      els.offerRow.style.display = 'none';
      modal.style.display = 'flex';
      preview(''); // Auto-apply any offer + fetch the true base.
    },
    close: close
  };

  w.NitCheckout = NitCheckout;
})(window);
