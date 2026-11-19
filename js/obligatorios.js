/*
 * Marca con un asterisco rojo los campos obligatorios de los formularios (clase form-card) y agrega la
 * leyenda «* Campo obligatorio». Solo actua en formularios con tres o mas campos: en el inicio de sesion
 * y en la recuperacion todos los campos son obligatorios y el asterisco no informa nada.
 * Los campos se detectan por el atributo required del navegador, asi que no hay lista que mantener.
 */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('form.form-card'), function (form) {
      var campos = Array.prototype.filter.call(form.querySelectorAll('input, select, textarea'), function (c) {
        return c.type !== 'hidden' && c.type !== 'submit' && c.type !== 'checkbox' && c.offsetParent !== null;
      });
      if (campos.length < 3) return;
      var marcados = 0;
      Array.prototype.forEach.call(form.querySelectorAll('label'), function (etiqueta) {
        var control = etiqueta.querySelector('input, select, textarea');
        if (!control || !control.required || etiqueta.querySelector('.req')) return;
        var marca = document.createElement('span');
        marca.className = 'req';
        marca.setAttribute('aria-hidden', 'true');
        marca.textContent = ' *';
        if (etiqueta.classList.contains('check-label')) {
          var texto = etiqueta.querySelector('span');
          (texto || etiqueta).appendChild(marca);
        } else if (etiqueta.firstChild && etiqueta.firstChild.nodeType === 3) {
          // el texto y el asterisco van en un mismo elemento, para que no queden en lineas distintas
          var envoltura = document.createElement('span');
          etiqueta.insertBefore(envoltura, etiqueta.firstChild);
          envoltura.appendChild(envoltura.nextSibling);
          envoltura.appendChild(marca);
        } else {
          etiqueta.insertBefore(marca, control);
        }
        marcados++;
      });
      if (marcados > 0 && !form.querySelector('.nota-obligatorio')) {
        var nota = document.createElement('p');
        nota.className = 'nota-obligatorio';
        nota.textContent = '* Campo obligatorio';
        form.insertBefore(nota, form.firstChild);
      }
    });
  });
})();
