import L from "leaflet";
import "leaflet/dist/leaflet.css";

const TILE_URL = "https://tile.openstreetmap.org/{z}/{x}/{y}.png";
const NOMINATIM_URL = "https://nominatim.openstreetmap.org/search";

function coordinate(value, minimum, maximum) {
  const number = Number(value);
  return Number.isFinite(number) && number >= minimum && number <= maximum ? number : null;
}

function setupMapPicker(root) {
  const openButton = root.querySelector("[data-map-open]");
  const openButtonLabel = openButton && openButton.querySelector("[data-map-open-label]");
  const setOpenButtonLabel = (label) => {
    if (openButtonLabel) openButtonLabel.textContent = label;
    else if (openButton) openButton.textContent = label;
  };
  const body = root.querySelector("[data-map-body]");
  const canvas = root.querySelector("[data-map-canvas]");
  const status = root.querySelector("[data-map-status]");
  const latitudeField = root.querySelector("[data-map-latitude]") || document.querySelector("[data-map-latitude]");
  const longitudeField = root.querySelector("[data-map-longitude]") || document.querySelector("[data-map-longitude]");
  const clearButton = root.querySelector("[data-map-clear]");
  const geolocateButton = root.querySelector("[data-map-geolocate]");
  const searchInput = root.querySelector("[data-map-search-input]");
  const searchButton = root.querySelector("[data-map-search-button]");
  const results = root.querySelector("[data-map-results]");
  if (!openButton || !body || !canvas || !status || !latitudeField || !longitudeField) return;

  let map = null;
  let marker = null;
  let lastSearchAt = 0;
  let activeSearch = null;

  function setStatus(message) {
    status.textContent = message;
  }

  function mapIcon() {
    return L.divIcon({
      className: "jluxe-map-pin",
      html: '<span class="jluxe-map-pin__dot" aria-hidden="true"></span>',
      iconSize: [36, 44],
      iconAnchor: [18, 42],
    });
  }

  function setSelectedLocation(lat, lng, message) {
    const safeLat = coordinate(lat, -90, 90);
    const safeLng = coordinate(lng, -180, 180);
    if (safeLat === null || safeLng === null) return;

    latitudeField.value = safeLat.toFixed(6);
    longitudeField.value = safeLng.toFixed(6);
    if (map) {
      if (marker) marker.setLatLng([safeLat, safeLng]);
      else {
        marker = L.marker([safeLat, safeLng], { draggable: true, icon: mapIcon() }).addTo(map);
        marker.on("dragend", () => {
          const point = marker.getLatLng();
          setSelectedLocation(point.lat, point.lng, "موقعیت نشانگر ذخیره شد.");
        });
      }
    }
    if (clearButton) clearButton.hidden = false;
    setStatus(message || "موقعیت روی نقشه انتخاب شد. برای محاسبهٔ ارسال، نشانی نوشتاری را هم تکمیل کنید.");
  }

  function initializeMap() {
    if (map) {
      window.requestAnimationFrame(() => map.invalidateSize());
      return;
    }
    const centerLat = coordinate(root.getAttribute("data-center-lat"), -90, 90) ?? 35.6892;
    const centerLng = coordinate(root.getAttribute("data-center-lng"), -180, 180) ?? 51.389;
    map = L.map(canvas, {
      attributionControl: true,
      zoomControl: false,
      scrollWheelZoom: false,
      tap: true,
    }).setView([centerLat, centerLng], 5);
    L.control.zoom({ position: "bottomright" }).addTo(map);
    L.tileLayer(TILE_URL, {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
    }).addTo(map);
    map.on("click", (event) => setSelectedLocation(event.latlng.lat, event.latlng.lng));

    const initialLat = coordinate(latitudeField.value, -90, 90);
    const initialLng = coordinate(longitudeField.value, -180, 180);
    if (initialLat !== null && initialLng !== null) {
      setSelectedLocation(initialLat, initialLng, "موقعیت ثبت‌شده نمایش داده شد.");
      map.setView([initialLat, initialLng], 15);
    }
    window.requestAnimationFrame(() => map.invalidateSize());
  }

  openButton.addEventListener("click", () => {
    if (!body.hidden) {
      body.hidden = true;
      openButton.setAttribute("aria-expanded", "false");
      setOpenButtonLabel("بازکردن نقشه");
      return;
    }
    body.hidden = false;
    openButton.setAttribute("aria-expanded", "true");
    setOpenButtonLabel("بستن نقشه");
    try {
      initializeMap();
    } catch (error) {
      setStatus("بارگذاری نقشه انجام نشد؛ نشانی نوشتاری همچنان قابل استفاده است.");
    }
  });

  if (clearButton) {
    clearButton.addEventListener("click", () => {
      latitudeField.value = "";
      longitudeField.value = "";
      if (marker && map) map.removeLayer(marker);
      marker = null;
      clearButton.hidden = true;
      setStatus("موقعیت نقشه پاک شد؛ نشانی نوشتاری همچنان قابل استفاده است.");
    });
  }

  if (geolocateButton) {
    geolocateButton.addEventListener("click", () => {
      if (!navigator.geolocation) {
        setStatus("مرورگر این دستگاه موقعیت‌یابی را پشتیبانی نمی‌کند. می‌توانید نشانگر نقشه را دستی جابه‌جا کنید.");
        return;
      }
      geolocateButton.disabled = true;
      setStatus("در حال دریافت موقعیت دستگاه…");
      navigator.geolocation.getCurrentPosition(
        (position) => {
          geolocateButton.disabled = false;
          const lat = position.coords.latitude;
          const lng = position.coords.longitude;
          setSelectedLocation(lat, lng);
          if (map) map.setView([lat, lng], 16);
        },
        () => {
          geolocateButton.disabled = false;
          setStatus("دریافت موقعیت انجام نشد. دسترسی مرورگر را بررسی کنید یا نشانگر را روی نقشه جابه‌جا کنید.");
        },
        { enableHighAccuracy: false, maximumAge: 30000, timeout: 10000 },
      );
    });
  }

  async function searchPlaces() {
    const query = searchInput ? searchInput.value.trim() : "";
    if (query.length < 2) {
      setStatus("برای جست‌وجو، نام شهر یا محله را وارد کنید.");
      return;
    }
    if (activeSearch) activeSearch.abort();
    activeSearch = new AbortController();
    if (searchButton) searchButton.disabled = true;
    if (results) {
      results.hidden = true;
      results.replaceChildren();
    }
    setStatus("در حال جست‌وجوی مکان…");

    // Respect the public Nominatim service's one-request-per-second guidance.
    const wait = Math.max(0, 1100 - (Date.now() - lastSearchAt));
    if (wait) await new Promise((resolve) => window.setTimeout(resolve, wait));
    lastSearchAt = Date.now();

    const url = new URL(NOMINATIM_URL);
    url.searchParams.set("format", "jsonv2");
    url.searchParams.set("limit", "5");
    url.searchParams.set("countrycodes", "ir");
    url.searchParams.set("accept-language", "fa");
    url.searchParams.set("q", query);

    try {
      const response = await fetch(url, {
        method: "GET",
        mode: "cors",
        credentials: "omit",
        cache: "no-store",
        referrerPolicy: "strict-origin",
        headers: { Accept: "application/json" },
        signal: activeSearch.signal,
      });
      if (!response.ok) throw new Error("search unavailable");
      const places = await response.json();
      if (!Array.isArray(places) || !places.length) {
        setStatus("مکانی پیدا نشد. نام دیگری جست‌وجو کنید یا نشانگر را دستی روی نقشه قرار دهید.");
        return;
      }
      if (!results) return;
      places.slice(0, 5).forEach((place) => {
        const lat = coordinate(place.lat, -90, 90);
        const lng = coordinate(place.lon, -180, 180);
        if (lat === null || lng === null) return;
        const item = document.createElement("li");
        const button = document.createElement("button");
        button.type = "button";
        button.textContent = String(place.display_name || "مکان پیدا شده");
        button.addEventListener("click", () => {
          setSelectedLocation(lat, lng);
          map.setView([lat, lng], 15);
          results.hidden = true;
          results.replaceChildren();
        });
        item.append(button);
        results.append(item);
      });
      results.hidden = results.childElementCount === 0;
      setStatus(results.hidden ? "مکانی پیدا نشد؛ جست‌وجوی دیگری انجام دهید یا نشانگر را دستی جابه‌جا کنید." : "یک نتیجه را انتخاب کنید یا نقطهٔ دلخواه را روی نقشه لمس کنید.");
    } catch (error) {
      if (error && error.name === "AbortError") return;
      setStatus("جست‌وجوی نقشه در دسترس نیست. می‌توانید نشانگر را دستی جابه‌جا کنید یا فقط نشانی نوشتاری را وارد کنید.");
    } finally {
      if (searchButton) searchButton.disabled = false;
    }
  }

  if (searchButton) searchButton.addEventListener("click", searchPlaces);
  if (searchInput) {
    searchInput.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        searchPlaces();
      }
    });
  }
}

document.querySelectorAll("[data-jluxe-map-picker]").forEach(setupMapPicker);
