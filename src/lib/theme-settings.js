export function getThemeSettings() {
  return typeof window === "undefined" ? {} : (window.JLuxeThemeSettings ?? {});
}
export { getThemeSettings as g };
