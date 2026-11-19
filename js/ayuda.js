/*
 * Manual de uso dentro de la pagina (includes/ayuda.php).
 * El boton fijo abre y cierra el panel lateral; al abrirlo se despliega la seccion que
 * corresponde a la pagina actual. Esc o la equis lo cierran. El buscador filtra las
 * secciones por su texto. El panel no bloquea la pagina: se puede seguir trabajando.
 *
 * El boton verde abre el asistente de ayuda (#chat-panel): preguntas frecuentes con respuesta fija,
 * un cuadro de texto que se compara con palabras clave y, si no hay respuesta, el paso a una persona
 * por WhatsApp. Si este script no corre, el boton sigue siendo un enlace normal a WhatsApp.
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

    var chat = document.getElementById('chat-panel');
    var chatBoton = document.getElementById('chat-boton');

    function abrir() {
      if (chat && !chat.hidden) cerrarChat(false);
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

    /* ---------------- asistente de ayuda ---------------- */
    var preguntas = [];
    var mensajes, opciones, formChat, textoChat, whatsapp = '';
    function cerrarChat(devolverFoco) {
      chat.hidden = true;
      if (chatBoton) chatBoton.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('ayuda-abierta');
      if (devolverFoco !== false && chatBoton) chatBoton.focus();
    }
    if (chat && chatBoton) {
      try { preguntas = JSON.parse(chat.getAttribute('data-preguntas') || '[]'); } catch (e) { preguntas = []; }
      whatsapp = chat.getAttribute('data-whatsapp') || '';
      mensajes = chat.querySelector('.chat-mensajes');
      opciones = chat.querySelector('.chat-opciones');
      formChat = chat.querySelector('.chat-form');
      textoChat = chat.querySelector('.chat-texto');

      var linkWhatsapp = function (duda) {
        var a = document.createElement('a');
        a.className = 'chat-wa';
        var texto = 'Hola, tengo una duda sobre SIRAD' + (duda ? ': ' + duda : '.');
        a.href = whatsapp + (whatsapp.indexOf('?') === -1 ? '?' : '&') + 'text=' + encodeURIComponent(texto);
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = 'Hablar con una persona del programa (WhatsApp)';
        return a;
      };
      var burbuja = function (tipo, texto, extras) {
        var d = document.createElement('div');
        d.className = 'chat-burbuja chat-' + tipo;
        var p = document.createElement('p');
        p.textContent = texto;
        d.appendChild(p);
        (extras || []).forEach(function (e) { d.appendChild(e); });
        mensajes.appendChild(d);
        mensajes.scrollTop = mensajes.scrollHeight;
      };
      var responder = function (item, duda) {
        var extras = [];
        if (item && item[3]) {
          var a = document.createElement('a');
          a.className = 'chat-enlace';
          a.href = item[3][1];
          a.textContent = item[3][0];
          extras.push(a);
        }
        if (item) {
          burbuja('bot', item[1], extras);
          var ayuda = document.createElement('p');
          ayuda.className = 'chat-mas';
          ayuda.appendChild(document.createTextNode('¿No era eso? '));
          ayuda.appendChild(linkWhatsapp(duda));
          mensajes.lastChild.appendChild(ayuda);
        } else {
          burbuja('bot', 'No tengo una respuesta para eso. Puedes elegir una de las preguntas de abajo o hablar con una persona del programa.', [linkWhatsapp(duda)]);
        }
        mensajes.scrollTop = mensajes.scrollHeight;
      };
      var mejor = function (texto) {
        var q = normalizar(texto);
        var ganador = null, puntos = 0;
        preguntas.forEach(function (p) {
          var n = 0;
          (p[2] || []).forEach(function (k) { if (q.indexOf(normalizar(k)) !== -1) n++; });
          if (n > puntos) { puntos = n; ganador = p; }
        });
        return ganador;
      };
      var iniciar = function () {
        if (mensajes.childNodes.length) return;
        burbuja('bot', 'Hola, soy el asistente de ayuda de SIRAD. Elige una pregunta o escribe tu duda; si no la resuelvo, te paso con una persona del programa.');
        preguntas.forEach(function (p) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'chat-chip';
          b.textContent = p[0];
          b.addEventListener('click', function () { burbuja('yo', p[0]); responder(p, p[0]); });
          opciones.appendChild(b);
        });
      };
      var abrirChat = function () {
        if (!panel.hidden) { panel.hidden = true; boton.setAttribute('aria-expanded', 'false'); }
        chat.hidden = false;
        chatBoton.setAttribute('aria-expanded', 'true');
        document.body.classList.add('ayuda-abierta');
        iniciar();
        if (window.matchMedia('(min-width: 641px)').matches) textoChat.focus();
      };
      chatBoton.setAttribute('aria-expanded', 'false');
      chatBoton.addEventListener('click', function (e) {
        e.preventDefault();
        chat.hidden ? abrirChat() : cerrarChat();
      });
      chat.querySelector('.ayuda-cerrar').addEventListener('click', cerrarChat);
      formChat.addEventListener('submit', function (e) {
        e.preventDefault();
        var t = textoChat.value.trim();
        if (!t) return;
        textoChat.value = '';
        burbuja('yo', t);
        responder(mejor(t), t);
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !chat.hidden) cerrarChat();
      });
    }
  });
})();
