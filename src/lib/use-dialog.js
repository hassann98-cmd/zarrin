import { useEffect, useRef } from "react";
import { activateDialog } from "./input.js";
export function useDialog(open, dialog, close) {
  const currentClose = useRef(close);
  currentClose.current = close;
  useEffect(() => {
    if (open && dialog.current)
      return activateDialog(dialog.current, () => currentClose.current());
  }, [open, dialog]);
}
