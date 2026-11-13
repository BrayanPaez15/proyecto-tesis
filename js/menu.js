/* Menu del encabezado: el boton de tres rayas abre y cierra los enlaces (computador y celular). */
(function () {
    var cab = document.querySelector('header');
    var boton = cab ? cab.querySelector('.menu-toggle') : null;
    if (!boton) { return; }
    function poner(abierto) {
        cab.classList.toggle('menu-abierto', abierto);
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        boton.setAttribute('aria-label', abierto ? 'Cerrar menú' : 'Abrir menú');
    }
    boton.addEventListener('click', function () { poner(!cab.classList.contains('menu-abierto')); });
    document.addEventListener('click', function (e) { if (!cab.contains(e.target)) { poner(false); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && cab.classList.contains('menu-abierto')) { poner(false); boton.focus(); } });
})();
