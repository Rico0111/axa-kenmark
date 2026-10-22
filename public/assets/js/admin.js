(() => {
  "use strict";

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    const message = form.getAttribute("data-confirm");
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  const source = document.querySelector("[data-slug-source]");
  const target = document.querySelector("[data-slug-target]");
  if (!(source instanceof HTMLInputElement) || !(target instanceof HTMLInputElement)) return;

  let manuallyEdited = target.value.trim() !== "";

  const slugify = (value) => value
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 170);

  target.addEventListener("input", () => {
    manuallyEdited = true;
  });

  source.addEventListener("input", () => {
    if (manuallyEdited) return;
    target.value = slugify(source.value);
  });
})();
