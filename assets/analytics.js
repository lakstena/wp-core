/* GA4 įvykiai ir slapukų baneris. Be bibliotekų. Konfigūracija: window.lkAnalytics = {lang, buy, maps[]} (analytics.php). */
(function () {
	'use strict';
	var cfg = window.lkAnalytics || {};
	var lang = cfg.lang || document.documentElement.lang.slice(0, 2) || 'lt';
	var COOKIE = 'lk_consent', DAYS = 180;

	function getCookie() {
		var m = document.cookie.match(/(?:^|; )lk_consent=(granted|denied)/);
		return m ? m[1] : '';
	}
	function setCookie(v) {
		document.cookie = COOKIE + '=' + v + '; max-age=' + DAYS * 86400 + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}
	function track(name, params) {
		if (typeof window.gtag !== 'function') { return; }
		params = params || {};
		params.lang = lang;
		window.gtag('event', name, params);
	}

	/* Baneris */
	var banner = document.getElementById('lk-consent');
	if (banner) {
		if (!getCookie()) { banner.hidden = false; }
		banner.addEventListener('click', function (e) {
			var b = e.target.closest('[data-lk-consent]');
			if (!b) { return; }
			var v = b.getAttribute('data-lk-consent') === 'granted' ? 'granted' : 'denied';
			setCookie(v);
			window.gtag('consent', 'update', { analytics_storage: v });
			if (v === 'granted' && typeof window.lkLoadGtag === 'function') { window.lkLoadGtag(); }
			banner.hidden = true;
		});
	}
	/* Pakartotinis pasirinkimas: bet kuri nuoroda / mygtukas su data-lk-consent-open */
	document.addEventListener('click', function (e) {
		if (banner && e.target.closest('[data-lk-consent-open]')) { e.preventDefault(); banner.hidden = false; }
	});

	/* Įvykiai. data-lk-event ir data-lk-loc ant nuorodos viršija automatinį atpažinimą (tel:, mailto:, žemėlapio nuorodos). */
	function location_of(a) {
		var l = a.getAttribute('data-lk-loc');
		if (l) { return l; }
		if (a.closest('.lk-footer')) { return 'footer'; }
		if (a.closest('.lk-header')) { return 'header'; }
		if (a.closest('.lk-prices, [id*="kain"], [id*="price"]')) { return 'prices'; }
		if (a.closest('.lk-contact-buttons, #kontaktai')) { return 'contact'; }
		return 'content';
	}
	function is_map(href) {
		if (/^https?:\/\/(www\.)?(google\.[a-z.]+\/maps|maps\.google\.|maps\.app\.goo\.gl|goo\.gl\/maps|g\.page)/i.test(href)) { return true; }
		return (cfg.maps || []).some(function (u) { return u && href.indexOf(u) === 0; });
	}
	document.addEventListener('click', function (e) {
		var a = e.target.closest('a[href]');
		if (!a) { return; }
		var href = a.getAttribute('href') || '';
		var ev = a.getAttribute('data-lk-event');
		if (!ev) {
			if (/^tel:/i.test(href)) { ev = 'click_tel'; }
			else if (/^mailto:/i.test(href)) { ev = 'click_email'; }
			else if (is_map(href)) { ev = 'click_directions'; }
		}
		if (ev === 'click_tel' || ev === 'click_email') { track(ev, { location: location_of(a) }); }
		else if (ev === 'click_directions') { track(ev); }
	});

	if (cfg.buy) { track('view_buying'); }
})();
