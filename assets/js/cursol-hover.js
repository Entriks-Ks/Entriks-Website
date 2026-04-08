(function($) {
	"use strict";

	$(document).ready(function() {
		
		const cursor = document.querySelectorAll(".cursor");
const cursorHover = document.querySelector(".cursor-hover");
const preview = document.querySelector(".preview");



gsap.set(cursor, {
  xPercent: -50,
  yPercent: -50,
  opacity: 1
});

gsap.set(cursorHover, {
  xPercent: -50,
  yPercent: -50,
  scale: 0,
  opacity: 0
});

const setX = gsap.quickTo(cursor, "x", {
  duration: 0.6,
  ease: "expo"
});

const setY = gsap.quickTo(cursor, "y", {
  duration: 0.6,
  ease: "expo"
});

window.addEventListener("mousemove", (e) => {
  setX(e.pageX);
  setY(e.pageY);
});

const tl = gsap.timeline({
  paused: true,
  defaults: {
    duration: 0.3,
    ease: "sine.inOut"
  }
});

tl.to(cursorHover, {
  scale: 1,
  opacity: 1
});

preview.addEventListener("mouseover", () => tl.play());
preview.addEventListener("mouseout", () => tl.reverse());
	});

})(jQuery);