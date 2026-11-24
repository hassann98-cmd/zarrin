import { useEffect, useRef, useState } from "react";

function jluxeHaptic() {
  try {
    if (typeof navigator !== "undefined" && typeof navigator.vibrate === "function") navigator.vibrate(12);
  } catch {
    // Haptics are optional and must never block navigation.
  }
}

/** Immediate touch feedback; the short release tail survives drawer/route opening. */
export function useNavPress() {
  const [pressed, setPressed] = useState(null);
  const timer = useRef();
  const clear = () => { clearTimeout(timer.current); setPressed(null); };
  useEffect(() => {
    window.addEventListener("pageshow", clear);
    return () => { clearTimeout(timer.current); window.removeEventListener("pageshow", clear); };
  }, []);
  return (id) => ({
    "data-nav-pressed": pressed === id ? "true" : undefined,
    onPointerDown: (event) => {
      if (event.button !== 0) return;
      if (event.pointerType !== "mouse") jluxeHaptic();
      clearTimeout(timer.current);
      setPressed(id);
      timer.current = setTimeout(clear, 1500);
    },
    onPointerUp: () => { clearTimeout(timer.current); timer.current = setTimeout(clear, 450); },
    onPointerCancel: clear,
    onBlur: clear,
  });
}
