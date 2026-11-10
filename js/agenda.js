/**
 * Agenda de disponibilidad: agrega y quita filas de horario en los
 * formularios que usan includes/agenda.php (Artistas y Talleristas).
 */
document.querySelectorAll('[data-agenda]').forEach(function (caja) {
  var filas = caja.querySelector('.agenda-rows');
  var plantilla = caja.querySelector('.agenda-template');

  caja.querySelector('.agenda-add').addEventListener('click', function () {
    filas.appendChild(plantilla.content.cloneNode(true));
  });

  caja.addEventListener('click', function (e) {
    if (e.target.classList.contains('agenda-quitar')) {
      e.target.closest('.agenda-row').remove();
    }
  });
});
