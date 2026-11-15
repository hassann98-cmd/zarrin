import { useEffect, useState } from "react";
import { getThemeSettings } from "./theme-settings.js";

function currentStatus() {
  return typeof window !== "undefined" && typeof window.jluxeCurrentAuthStatus === "boolean"
    ? window.jluxeCurrentAuthStatus
    : getThemeSettings().auth?.isLoggedIn ?? false;
}

export function useAuthStatus() {
  const [loggedIn, setLoggedIn] = useState(currentStatus);
  useEffect(() => {
    const sync = () => setLoggedIn(currentStatus());
    window.addEventListener("jluxe:auth-status", sync);
    sync(); // reconcile a session response that arrived before hydration
    return () => window.removeEventListener("jluxe:auth-status", sync);
  }, []);
  return loggedIn;
}
