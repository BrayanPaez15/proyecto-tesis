/*
 * Medidor de contrasena: marca cada requisito (includes/password.php) mientras la persona
 * escribe y muestra el nivel Debil / Media / Fuerte. Es solo una guia; el servidor valida.
 * Se activa en los campos con data-pw. La lista de requisitos ([data-pw-rules]) es un mensaje
 * flotante que aparece al pasar el cursor sobre el campo (en pantallas tactiles, al tocarlo).
 * La barra y el nivel ([data-pw-meter]) aparecen debajo al hacer clic en el campo y se ocultan
 * al salir si esta vacio o ya cumple todos los requisitos.
 */
(function () {
  function reglas(lista) {
    return Array.prototype.map.call(lista.querySelectorAll('li'), function (li) {
      var re = null;
      try { re = new RegExp(li.getAttribute('data-regex'), 'u'); } catch (e) { re = null; }
      return { li: li, re: re };
    });
  }
  function activar(campo) {
    var form = campo.closest('form');
    var lista = form.querySelector('[data-pw-rules]');
    var medidor = form.querySelector('[data-pw-meter]');
    if (!lista || !medidor) return;
    var rs = reglas(lista);
    var barra = medidor.querySelector('.pw-bar span');
    var nivel = medidor.querySelector('.pw-level strong');
    // el mensaje flotante vive en <body> para que ninguna tarjeta lo recorte
    document.body.appendChild(lista);
    if (!campo.id) campo.id = 'pw-' + Math.random().toString(36).slice(2, 8);
    lista.id = lista.id || campo.id + '-requisitos';
    campo.setAttribute('aria-describedby', lista.id);
    function mostrar(si) { medidor.hidden = !si; }
    function flotante(si) {
      lista.hidden = !si;
      if (!si) return;
      var r = campo.getBoundingClientRect();
      lista.style.minWidth = Math.min(r.width, 320) + 'px';
      var alto = lista.offsetHeight;
      var arriba = r.top - alto - 8;
      lista.style.left = Math.max(8, Math.min(r.left, window.innerWidth - lista.offsetWidth - 8)) + 'px';
      lista.style.top = (arriba >= 8 ? arriba : r.bottom + 8) + 'px';
    }
    function actualizar() {
      var v = campo.value, cumplidas = 0;
      rs.forEach(function (r) {
        var ok = r.re ? r.re.test(v) : false;
        r.li.classList.toggle('ok', ok);
        if (ok) cumplidas++;
      });
      var todas = cumplidas === rs.length;
      var texto = 'Débil', clase = 'debil', ancho = Math.max(8, cumplidas * 16);
      if (todas && v.length >= 12) { texto = 'Fuerte'; clase = 'fuerte'; ancho = 100; }
      else if (todas) { texto = 'Media'; clase = 'media'; ancho = 70; }
      if (v === '') { texto = '—'; clase = ''; ancho = 0; }
      barra.style.width = ancho + '%';
      barra.className = clase;
      nivel.textContent = texto;
    }
    campo.addEventListener('input', actualizar);
    var tactil = window.matchMedia && window.matchMedia('(hover: none)').matches;
    campo.addEventListener('mouseenter', function () { flotante(true); });
    campo.addEventListener('mouseleave', function () { if (!tactil) flotante(false); });
    // La barra y el nivel: al hacer clic en el campo; al salir se ocultan si esta vacio o ya cumple todo
    campo.addEventListener('focus', function () { mostrar(true); if (tactil) flotante(true); });
    campo.addEventListener('blur', function () {
      var cumple = rs.every(function (r) { return r.li.classList.contains('ok'); });
      // en el registro el nivel flota sobre los campos de abajo: se oculta siempre al salir
      if (campo.value === '' || cumple || campo.closest('.campo-pw')) mostrar(false);
      if (tactil) flotante(false);
    });
    window.addEventListener('scroll', function () { flotante(false); }, true);
    actualizar();
    if (document.activeElement === campo) mostrar(true);
  }
  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('input[data-pw]'), activar);
  });
})();
