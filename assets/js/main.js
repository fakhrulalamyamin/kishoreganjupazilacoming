(function () {
  'use strict';

  document.documentElement.classList.remove('no-js');

  var BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
  function toBn(n) {
    return String(n).replace(/\d/g, function (d) { return BN_DIGITS[d]; });
  }

  // সাল বাংলায়
  var yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = toBn(new Date().getFullYear());

  // স্ক্রল করলে উপাদানগুলো ভেসে উঠবে
  var reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('is-visible');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('is-visible'); });
  }

  // অগ্রগতি বার
  var progress = document.querySelector('.progress');
  if (progress) {
    var bar = progress.querySelector('.progress-bar');
    var num = progress.querySelector('.progress-num');
    var target = parseInt(num.getAttribute('data-target'), 10) || 0;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    setTimeout(function () {
      bar.style.width = target + '%';
      if (reduce) { num.textContent = toBn(target) + '%'; return; }
      var start = null, dur = 2000;
      function step(ts) {
        if (!start) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        num.textContent = toBn(Math.round(eased * target)) + '%';
        if (p < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    }, 900);
  }

  // আপডেট পেতে ইমেইল ফর্ম
  var form = document.getElementById('notifyForm');
  var msg = document.getElementById('formMsg');
  if (form && window.fetch && window.FormData) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var email = form.email.value.trim();
      msg.className = 'form-msg';

      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        msg.textContent = 'অনুগ্রহ করে একটি সঠিক ইমেইল ঠিকানা লিখুন।';
        msg.classList.add('err');
        form.email.focus();
        return;
      }

      var btn = form.querySelector('button');
      btn.disabled = true;
      btn.classList.add('is-loading');

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          msg.textContent = data.message;
          msg.classList.add(data.ok ? 'ok' : 'err');
          if (data.ok) form.reset();
        })
        .catch(function () {
          msg.textContent = 'দুঃখিত, এই মুহূর্তে সংযোগ করা যাচ্ছে না। পরে আবার চেষ্টা করুন।';
          msg.classList.add('err');
        })
        .then(function () {
          btn.disabled = false;
          btn.classList.remove('is-loading');
        });
    });
  }

  // JS ছাড়া ফর্ম জমা দিলে ফিরে আসার বার্তা
  var params = new URLSearchParams(window.location.search);
  if (msg && params.has('subscribed')) {
    var ok = params.get('subscribed') === '1';
    msg.textContent = ok ? 'ধন্যবাদ! ওয়েবসাইট চালু হলে আপনাকে জানানো হবে।' : 'দুঃখিত, ইমেইলটি গ্রহণ করা যায়নি। আবার চেষ্টা করুন।';
    msg.className = 'form-msg ' + (ok ? 'ok' : 'err');
  }
})();
