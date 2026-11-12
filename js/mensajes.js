// Mensajes de las pantallas de acceso (login, registro y recuperar contrasena):
// los avisos de exito y de error se desvanecen solos pasados unos segundos
// (mas tiempo cuanto mas largo es el texto) y se limpia de la URL el parametro
// que los origino, para que no reaparezcan al recargar la pagina.
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".auth-success, .auth-error").forEach((aviso) => {
    // los avisos con datos de contacto (data-persistente) duran mas para dar tiempo de leerlos y abrir el enlace;
    // el boton verde de WhatsApp sigue disponible despues
    const ms = aviso.hasAttribute("data-persistente")
      ? 12000
      : Math.min(9000, Math.max(5000, aviso.textContent.trim().length * 50));
    setTimeout(() => {
      aviso.style.transition = "opacity 0.5s ease, max-height 0.5s ease, margin 0.5s ease, padding 0.5s ease";
      aviso.style.overflow = "hidden";
      aviso.style.maxHeight = aviso.offsetHeight + "px";
      void aviso.offsetHeight;
      aviso.style.opacity = "0";
      aviso.style.maxHeight = "0";
      aviso.style.margin = "0";
      aviso.style.paddingTop = "0";
      aviso.style.paddingBottom = "0";
      setTimeout(() => aviso.remove(), 600);
    }, ms);
  });

  const params = new URLSearchParams(window.location.search);
  let cambio = false;
  ["registrado", "restablecido", "aviso"].forEach((clave) => {
    if (params.has(clave)) {
      params.delete(clave);
      cambio = true;
    }
  });
  if (cambio) {
    const query = params.toString();
    window.history.replaceState({}, "", window.location.pathname + (query ? `?${query}` : ""));
  }
});
