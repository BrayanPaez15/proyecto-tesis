// Muestra una notificacion toast leyendo ?msg=&tipo= de la URL tras un
// redirect (guardar/actualizar/eliminar/exportar), y limpia esos
// parametros para que no reaparezca al recargar la pagina.
document.addEventListener("DOMContentLoaded", () => {
  const params = new URLSearchParams(window.location.search);
  const mensaje = params.get("msg");
  if (!mensaje) return;

  const tipo = params.get("tipo") === "error" ? "error" : "success";

  let contenedor = document.querySelector(".toast-container");
  if (!contenedor) {
    contenedor = document.createElement("div");
    contenedor.className = "toast-container";
    document.body.appendChild(contenedor);
  }

  const toast = document.createElement("div");
  toast.className = `toast ${tipo}`;
  toast.textContent = mensaje;
  contenedor.appendChild(toast);
  setTimeout(() => toast.remove(), Math.min(9000, Math.max(4000, mensaje.length * 60)));

  params.delete("msg");
  params.delete("tipo");
  const query = params.toString();
  const nuevaUrl = window.location.pathname + (query ? `?${query}` : "");
  window.history.replaceState({}, "", nuevaUrl);
});
