/**
 * R91b — مرورگرِ دسته‌بندی‌ها (برگهٔ «همه دسته‌بندی‌ها») به‌صورتِ React island.
 *
 * - سرور (inc/categories-page.php) همان HTML را کامل و بدونِ JS رندر می‌کند
 *   (?cat=ID ⇒ همان زیردسته‌ها)؛ این island فقط جابه‌جاییِ بدونِ رفرش را اضافه
 *   می‌کند. داده از <script type="application/json" id="jluxe-cats-data"> خوانده
 *   می‌شود — هیچ درخواستِ شبکه‌ای لازم نیست.
 * - حالتِ panel: دسته‌های اصلی در ستونِ کناری، زیردسته‌ها در پنل (مثلِ اپ).
 *   انتخاب با replaceState در آدرس می‌نشیند (رفرش/اشتراک همان را نشان می‌دهد).
 * - حالتِ stack: شبکهٔ دسته‌های اصلی ← لمس ← صفحهٔ زیردسته‌ها با «بازگشت»؛
 *   با pushState، پس دکمهٔ Back گوشی هم به دسته‌های اصلی برمی‌گردد.
 * بدونِ JSX تا در تست‌های Node (JSDOM) مستقیم قابلِ اجرا باشد.
 */
import React, {
  useCallback,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
} from "react";

const h = React.createElement;

export function readCategoriesData(doc) {
  const node = doc?.getElementById("jluxe-cats-data");
  if (!node) return null;
  try {
    const data = JSON.parse(node.textContent || "null");
    return data && Array.isArray(data.parents) ? data : null;
  } catch {
    return null;
  }
}

function catFromUrl(win) {
  try {
    return Number(new win.URL(win.location.href).searchParams.get("cat")) || 0;
  } catch {
    return 0;
  }
}

function urlWithCat(win, id) {
  const url = new win.URL(win.location.href);
  if (id) url.searchParams.set("cat", String(id));
  else url.searchParams.delete("cat");
  return url.pathname + url.search + url.hash;
}

function Media({ item, className, size, eager, iconClass = "jc-icon" }) {
  if (item.img && item.img.src) {
    return h(
      "span",
      { className, "aria-hidden": "true" },
      h("img", {
        src: item.img.src,
        srcSet: item.img.srcset || undefined,
        sizes: item.img.srcset ? `${size}px` : undefined,
        width: size,
        height: size,
        alt: "",
        decoding: "async",
        loading: eager ? undefined : "lazy",
      }),
    );
  }
  return h(
    "span",
    { className, "aria-hidden": "true" },
    h("span", {
      className: iconClass,
      dangerouslySetInnerHTML: { __html: item.svg || "" },
    }),
  );
}

function isPlainClick(event) {
  return !(
    event.defaultPrevented ||
    event.button ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey
  );
}

function Panel({ parent, data, mode, onBack, titleRef, anim }) {
  const kids = parent.children || [];
  return h(
    "section",
    {
      className: anim ? "jc-panel jc-anim" : "jc-panel",
      id: `jc-panel-${parent.id}`,
      "aria-labelledby": `jc-panel-${parent.id}-t`,
    },
    h(
      "div",
      { className: "jc-panel__head" },
      mode === "stack"
        ? h(
            "button",
            { type: "button", className: "jc-back", onClick: onBack },
            h("span", { "aria-hidden": "true" }, "→"),
            " ",
            data.labels.back,
          )
        : null,
      h(
        "h2",
        {
          className: "jc-panel__title",
          id: `jc-panel-${parent.id}-t`,
          tabIndex: -1,
          ref: titleRef,
        },
        parent.name,
      ),
      data.showAll && parent.allUrl
        ? h(
            "a",
            { className: "jc-panel__all", href: parent.allUrl },
            data.labels.all,
            " ",
            h("span", { "aria-hidden": "true" }, "←"),
          )
        : null,
    ),
    kids.length
      ? h(
          "ul",
          { className: "jc-children", role: "list" },
          kids.map((child, i) =>
            h(
              "li",
              { key: child.id },
              h(
                "a",
                { className: "jc-child", href: child.url },
                h(Media, {
                  item: child,
                  className: "jc-child__media",
                  size: data.childSize,
                  eager: i < 8,
                }),
                h("span", { className: "jc-child__name" }, child.name),
                data.showCount && child.count >= 0
                  ? h(
                      "span",
                      { className: "jc-child__count" },
                      child.countLabel,
                    )
                  : null,
              ),
            ),
          ),
        )
      : h("p", { className: "jc-panel__empty" }, data.labels.empty),
  );
}

export default function CategoriesBrowser() {
  const [data] = useState(() => readCategoriesData(globalThis.document));
  const winRef = useRef(globalThis.document?.defaultView || null);
  const byId = useRef(new Map());
  if (data && !byId.current.size)
    data.parents.forEach((p) => p.hasPanel && byId.current.set(p.id, p));
  const initial = () => {
    const fromUrl = data ? catFromUrl(winRef.current) : 0;
    if (fromUrl && byId.current.has(fromUrl)) return fromUrl;
    if (data && byId.current.has(data.active)) return data.active;
    return data && data.mode === "panel" ? data.defaultId || 0 : 0;
  };
  const [active, setActive] = useState(initial);
  const rootRef = useRef(null);
  const titleRef = useRef(null);
  const lastParent = useRef(0);
  const pushed = useRef(false);
  const focusAfter = useRef("");
  const moved = useRef(false); // انیمیشن فقط بعد از اولین جابه‌جایی (نه هنگامِ hydrate)

  const reveal = useCallback(() => {
    const win = winRef.current,
      el = rootRef.current;
    if (!win || !el || typeof el.getBoundingClientRect !== "function") return;
    const offset = data?.stickyHeader
      ? ((win.innerWidth || 0) >= 768 ? 90 : 72) + 12
      : 12;
    const top = el.getBoundingClientRect().top + (win.scrollY || 0) - offset;
    if ((win.scrollY || 0) > top) {
      const reduce =
        win.matchMedia &&
        win.matchMedia("(prefers-reduced-motion: reduce)").matches;
      try {
        win.scrollTo({ top, behavior: reduce ? "auto" : "smooth" });
      } catch {
        win.scrollTo(0, top);
      }
    }
  }, [data]);

  const select = useCallback(
    (id) => {
      const win = winRef.current;
      if (!data || !byId.current.has(id)) return false;
      lastParent.current = id;
      moved.current = true;
      setActive(id);
      if (win && win.history) {
        if (data.mode === "stack") {
          win.history.pushState({ jluxeCat: id }, "", urlWithCat(win, id));
          pushed.current = true;
          focusAfter.current = "title";
        } else {
          win.history.replaceState({ jluxeCat: id }, "", urlWithCat(win, id));
        }
      }
      reveal();
      return true;
    },
    [data, reveal],
  );

  const back = useCallback(() => {
    const win = winRef.current;
    focusAfter.current = "parent";
    moved.current = true;
    if (
      pushed.current &&
      win &&
      win.history &&
      win.history.state &&
      win.history.state.jluxeCat
    ) {
      pushed.current = false;
      win.history.back(); // popstate ⇒ setActive(0)
      return;
    }
    setActive(0);
    if (win && win.history)
      win.history.replaceState({ jluxeCat: 0 }, "", urlWithCat(win, 0));
    reveal();
  }, [reveal]);

  useEffect(() => {
    const win = winRef.current;
    if (!win || !data) return undefined;
    const onPop = () => {
      moved.current = true;
      const id = catFromUrl(win);
      if (id && byId.current.has(id)) setActive(id);
      else {
        if (data.mode === "stack") focusAfter.current = "parent";
        setActive(data.mode === "panel" ? data.defaultId || 0 : 0);
      }
    };
    win.addEventListener("popstate", onPop);
    return () => win.removeEventListener("popstate", onPop);
  }, [data]);

  useLayoutEffect(() => {
    const what = focusAfter.current;
    focusAfter.current = "";
    if (what === "title" && titleRef.current)
      titleRef.current.focus({ preventScroll: true });
    if (what === "parent" && rootRef.current && lastParent.current) {
      const link = rootRef.current.querySelector(
        `[data-jc-parent="${lastParent.current}"]`,
      );
      if (link) link.focus({ preventScroll: true });
    }
  }, [active]);

  // بدونِ داده ⇒ خطا تا mountIsland (data-jluxe-keep-ssr) همان HTMLِ سرور را برگرداند.
  if (!data) throw new Error("jluxe-cats-data missing or invalid");
  const current = active ? byId.current.get(active) : null;

  const parentLink = (p, i, className, inner) => {
    const props = { className, href: p.url };
    if (p.hasPanel) {
      props.href = p.catHref;
      props["data-jc-parent"] = p.id;
      props["aria-controls"] = `jc-panel-${p.id}`;
      if (p.id === active) props["aria-current"] = "true";
      props.onClick = (event) => {
        if (isPlainClick(event) && select(p.id)) event.preventDefault();
      };
    }
    return h("a", props, inner);
  };

  if (data.mode === "panel") {
    return h(
      "div",
      {
        className: "jc-browser jc-browser--panel",
        ref: rootRef,
        style: data.style,
      },
      h(
        "nav",
        { className: "jc-rail", "aria-label": data.labels.rail },
        h(
          "ul",
          { className: "jc-rail__list", role: "list" },
          data.parents.map((p, i) =>
            h(
              "li",
              { key: `${p.id}-${i}` },
              parentLink(
                p,
                i,
                `jc-rail__item${p.id === active && p.hasPanel ? " is-active" : ""}`,
                [
                  h(Media, {
                    key: "m",
                    item: p,
                    className: "jc-rail__icon",
                    size: data.railSize,
                    eager: i < 10,
                  }),
                  h("span", { key: "n", className: "jc-rail__name" }, p.name),
                ],
              ),
            ),
          ),
        ),
      ),
      h(
        "div",
        { className: "jc-panels", "aria-live": "polite" },
        current
          ? h(Panel, {
              key: current.id,
              parent: current,
              data,
              mode: "panel",
              titleRef,
              anim: moved.current,
            })
          : null,
      ),
    );
  }

  // stack
  return h(
    "div",
    {
      className: `jc-browser jc-browser--stack${current ? " is-drilled" : ""}`,
      ref: rootRef,
      style: data.style,
    },
    current
      ? h(
          "div",
          { className: "jc-panels" },
          h(Panel, {
            key: current.id,
            parent: current,
            data,
            mode: "stack",
            onBack: back,
            titleRef,
            anim: moved.current,
          }),
        )
      : h(
          "ul",
          {
            className: `jluxe-cats-grid jluxe-cats-grid--${data.gridLayout}${moved.current ? " jc-anim" : ""}`,
            style: data.gridStyle,
            role: "list",
          },
          data.parents.map((p, i) =>
            h(
              "li",
              { key: `${p.id}-${i}` },
              parentLink(p, i, "jluxe-cats-card", [
                h(Media, {
                  key: "m",
                  item: p,
                  className: "jluxe-cats-card__media",
                  size: data.gridSize,
                  eager: i < 6,
                  iconClass: "jluxe-cats-card__icon",
                }),
                h(
                  "span",
                  { key: "b", className: "jluxe-cats-card__body" },
                  h("span", { className: "jluxe-cats-card__name" }, p.name),
                  data.showCount && p.count >= 0
                    ? h(
                        "span",
                        { className: "jluxe-cats-card__count" },
                        p.countLabel,
                      )
                    : null,
                ),
              ]),
            ),
          ),
        ),
  );
}
