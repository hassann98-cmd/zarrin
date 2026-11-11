/** Load Leaflet only when the shopper explicitly opens the optional map. */
export function deferCheckoutMapLoad(picker, loadChunk = () => import("./checkout-map.js")) {
  const button = picker.querySelector("[data-map-open]");
  if (!button) return;

  let request = null;
  button.addEventListener("click", () => {
    if (request) return;
    request = loadChunk().then(
      () => button.click(),
      () => { request = null; },
    );
  });
}
