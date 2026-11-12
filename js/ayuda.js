/*
 * Manual de uso dentro de la pagina (includes/ayuda.php).
 * El boton fijo abre y cierra el panel lateral; al abrirlo se despliega la seccion que
 * corresponde a la pagina actual. Esc o la equis lo cierran. El buscador filtra las
 * secciones por su texto. El panel no bloquea la pagina: se puede seguir trabajando.
 */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var boton = document.getElementById('ayuda-boton');
    var panel = document.getElementById('ayuda-panel');
    if (!boton || !panel) return;
    var cerrar = panel.querySelector('.ayuda-cerrar');
    var buscar = document.getElementById('ayuda-buscar');
    var secciones = Array.prototype.slice.call(panel.querySelectorAll('.ayuda-seccion'));
    var vacio = panel.querySelector('.ayuda-vacio');
    var yaPosicionado = false;

    function abrir() {
      panel.hidden = false;
      boton.setAttribute('aria-expanded', 'true');
      document.body.classList.add('ayuda-abierta');
      if (!yaPosicionado) {
        var actual = panel.getAttribute('data-actual');
        var sec = actual ? document.getElementById('ayuda-sec-' + actual) : null;
        if (sec) {
          sec.open = true;
          var cuerpo = panel.querySelector('.ayuda-cuerpo');
          cuerpo.scrollTop = Math.max(0, sec.offsetTop - cuerpo.offsetTop - 8);
        }
        yaPosicionado = true;
      }
      if (window.matchMedia('(min-width: 641px)').matches && buscar) buscar.focus();
    }
    function cerrarPanel() {
      panel.hidden = true;
      boton.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('ayuda-abierta');
      boton.focus();
    }
    boton.addEventListener('click', function () { panel.hidden ? abrir() : cerrarPanel(); });
    cerrar.addEventListener('click', cerrarPanel);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !panel.hidden) cerrarPanel();
    });

    function normalizar(t) {
      return t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }
    if (buscar) {
      buscar.addEventListener('input', function () {
        var q = normalizar(buscar.value.trim());
        var visibles = 0;
        secciones.forEach(function (s) {
          var coincide = q === '' || normalizar(s.textContent).indexOf(q) !== -1;
          s.hidden = !coincide;
          if (coincide) visibles++;
          if (q !== '' && coincide) s.open = true;
        });
        if (vacio) vacio.hidden = visibles !== 0;
      });
    }
  });
})();
