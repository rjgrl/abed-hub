/**
 * Public team page: reveal sections on scroll (uses .team-reveal in style.css).
 */
(function () {
  var nodes = document.querySelectorAll(".team-reveal[data-reveal]");
  if (!nodes.length || !("IntersectionObserver" in window)) {
    nodes.forEach(function (el) {
      el.classList.add("is-visible");
    });
    return;
  }
  var io = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          io.unobserve(entry.target);
        }
      });
    },
    { root: null, rootMargin: "0px 0px -8% 0px", threshold: 0.08 }
  );
  nodes.forEach(function (el) {
    io.observe(el);
  });
})();
