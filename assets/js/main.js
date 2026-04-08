(function ($) {
	"use strict";
	$(document).ready(function () {
		$('[data-toggle="tooltip"]').tooltip();
		$('.player').mb_YTPlayer();
		$('.animate').scrolla();
		$('#gallery-masonary,.blog-masonry').imagesLoaded(function () {
			$('.mix-item-menu').on('click', 'button', function () {
				var filterValue = $(this).attr('data-filter');
				$grid.isotope({
					filter: filterValue
				});
			});
			$('.mix-item-menu button').on('click', function (event) {
				$(this).siblings('.active').removeClass('active');
				$(this).addClass('active');
				event.preventDefault();
			});
			var $grid = $('#gallery-masonary').isotope({
				itemSelector: '.gallery-item',
				percentPosition: true,
				masonry: {
					columnWidth: '.gallery-item',
				}
			});
			$('.blog-masonry').isotope({
				itemSelector: '.blog-item',
				percentPosition: true,
				masonry: {
					columnWidth: '.blog-item',
				}
			});
		});

		$('.timer').countTo();
		$('.fun-fact').appear(function () {
			$('.timer').countTo();
		}, {
			accY: -100
		});

		$(".popup-link").magnificPopup({
			type: 'image',
		});

		$(".popup-gallery").magnificPopup({
			type: 'image',
			gallery: {
				enabled: true
			},
		});

		$(".popup-youtube, .popup-vimeo, .popup-gmaps").magnificPopup({
			type: "iframe",
			mainClass: "mfp-fade",
			removalDelay: 160,
			preloader: false,
			fixedContentPos: false
		});

		$('.magnific-mix-gallery').each(function () {
			var $container = $(this);
			var $imageLinks = $container.find('.item');

			var items = [];
			$imageLinks.each(function () {
				var $item = $(this);
				var type = 'image';
				if ($item.hasClass('magnific-iframe')) {
					type = 'iframe';
				}
				var magItem = {
					src: $item.attr('href'),
					type: type
				};
				magItem.title = $item.data('title');
				items.push(magItem);
			});

			$imageLinks.magnificPopup({
				mainClass: 'mfp-fade',
				items: items,
				gallery: {
					enabled: true,
					tPrev: $(this).data('prev-text'),
					tNext: $(this).data('next-text')
				},
				type: 'image',
				callbacks: {
					beforeOpen: function () {
						var index = $imageLinks.index(this.st.el);
						if (-1 !== index) {
							this.goTo(index);
						}
					}
				}
			});
		});

		function animateElements() {
			$('.progressbar').each(function () {
				var elementPos = $(this).offset().top;
				var topOfWindow = $(window).scrollTop();
				var percent = $(this).find('.circle').attr('data-percent');
				var animate = $(this).data('animate');
				if (elementPos < topOfWindow + $(window).height() - 30 && !animate) {
					$(this).data('animate', true);
					$(this).find('.circle').circleProgress({
						value: percent / 100,
						size: 130,
						thickness: 13,
						lineCap: 'round',
						emptyFill: '#f1f1f1',
						fill: {
							gradient: ['#2667FF', '#6c19ef']
						}
					}).on('circle-animation-progress', function (event, progress, stepValue) {
						$(this).find('strong').text((stepValue * 100).toFixed(0) + "%");
					}).stop();
				}
			});

		}

		animateElements();
		$(window).scroll(animateElements);

		const servicesCarousel = new Swiper(".services-carousel", {
			loop: true,
			autoplay: true,
			freeMode: true,
			grabCursor: true,
			slidesPerView: 1,
			spaceBetween: 30,
			navigation: {
				nextEl: ".services-button-next",
				prevEl: ".services-button-prev"
			},
			breakpoints: {
				768: {
					slidesPerView: 2,
					spaceBetween: 50,
				},
				1400: {
					slidesPerView: 2.5,
					spaceBetween: 60,
				},

				1800: {
					spaceBetween: 60,
					slidesPerView: 2.8,
				},
			},
		});

		const testimonialOne = new Swiper(".testimonial-style-one-carousel", {
			loop: true,
			slidesPerView: 1,
			spaceBetween: 0,
			autoplay: true,

			pagination: {
				el: '.testimonial-one-pagination',
				type: 'fraction',
				clickable: true,
			},

			navigation: {
				nextEl: ".testimonial-one-button-next",
				prevEl: ".testimonial-one-button-prev"
			}
		});

		const projectStage = new Swiper(".project-center-stage-carousel", {
			loop: true,
			freeMode: true,
			grabCursor: true,
			slidesPerView: 1,
			centeredSlides: true,
			spaceBetween: 30,
			autoplay: true,
			pagination: {
				el: ".swiper-pagination",
				clickable: true,
			},
			navigation: {
				nextEl: ".project-button-next",
				prevEl: ".project-button-prev"
			},
			breakpoints: {
				991: {
					slidesPerView: 2,
					spaceBetween: 30,
					centeredSlides: false,
				},
				1200: {
					slidesPerView: 2.5,
					spaceBetween: 60,
				},
				1800: {
					slidesPerView: 2.8,
					spaceBetween: 80,
				},
			},
		});

		const swiperCounter = new Swiper(".banner-slide-counter", {
			direction: "vertical",
			loop: true,
			grabCursor: true,
			mousewheel: true,
			autoplay: true,
			speed: 1000,
			autoplay: {
				delay: 5000,
				disableOnInteraction: false,
			},

			pagination: {
				el: '.swiper-pagination',
				clickable: true,
			},

			navigation: {
				nextEl: ".swiper-button-next",
				prevEl: ".swiper-button-prev"
			}

		});

		$('.contact-form').each(function () {
			var formInstance = $(this);
			formInstance.submit(function () {

				var action = $(this).attr('action');

				var messageBox = formInstance.find('.alert-msg');
				messageBox.slideUp(750, function () {
					messageBox.hide();

					formInstance.find('button[type="submit"]')
						.after('<img src="assets/img/ajax-loader.gif" class="loader" />')
						.attr('disabled', 'disabled');

					$.post(action, {
						name: formInstance.find('input[name="name"]').val(),
						email: formInstance.find('input[name="email"]').val(),
						phone: formInstance.find('input[name="phone"]').val(),
						comments: formInstance.find('textarea[name="comments"]').val()
					},
						function (data) {
							$('#emailSuccessModal').modal('show');

							formInstance.find('img.loader').fadeOut('slow', function () {
								$(this).remove()
							});
							formInstance.find('button[type="submit"]').removeAttr('disabled');

							formInstance[0].reset();
						}
					);
				});
				return false;
			});
		});

		const link = document.querySelectorAll('.service-hover-item');
		const linkHoverReveal = document.querySelectorAll('.service-hover-wrapper');
		const linkImages = document.querySelectorAll('.service-hover-placeholder');
		for (let i = 0; i < link.length; i++) {
			link[i].addEventListener('mousemove', (e) => {
				linkHoverReveal[i].style.opacity = 1;
				linkHoverReveal[i].style.transform = `translate(-100%, -50% ) rotate(-3deg)`;
				linkImages[i].style.transform = 'scale(1, 1)';
				linkHoverReveal[i].style.left = e.clientX + "px";
			})
			link[i].addEventListener('mouseleave', (e) => {
				linkHoverReveal[i].style.opacity = 0;
				linkHoverReveal[i].style.transform = `translate(-50%, -50%) rotate(5deg)`;
				linkImages[i].style.transform = 'scale(0.8, 0.8)';
			})
		}

		const urlParams = new URL(window.location.href).searchParams;
		if (typeof ScrollSmoother !== 'undefined' && !urlParams.has('inner_frame') && !urlParams.has('edit')) {
			ScrollSmoother.create({
				content: ".viewport",
				smooth: 1
			});
		}
	});


	$(window).scroll(function () {
		var scroll = $(window).scrollTop();
		$("#js-hero").css({
			width: (100 + scroll / 18) + "%"
		})
		$(".bg-static").each(function () {
			var windowTop = $(window).scrollTop();
			var elementTop = $(this).offset().top;
			var leftPosition = windowTop - elementTop;
			$(this)
				.find(".bg-move")
				.css({
					left: leftPosition
				});
		});
	})
	function loader() {
		$(window).on('load', function () {
			$('#avrix-preloader').addClass('loaded');
			$("#loading").fadeOut(500);

			if ($('#avrix-preloader').hasClass('loaded')) {
				$('#preloader').delay(900).queue(function () {
					$(this).remove();
				});
			}
		});
	}
	loader();

})(jQuery);

document.addEventListener('DOMContentLoaded', function () {
	const langToggle = document.getElementById('langToggle');
	const langMenu = document.getElementById('langMenu');

	if (langToggle && langMenu) {
		langToggle.addEventListener('click', function (e) {
			e.stopPropagation();
			if (langMenu.style.display === 'block') {
				langMenu.style.display = 'none';
			} else {
				langMenu.style.display = 'block';
			}
		});

		document.addEventListener('click', function (e) {
			if (!langMenu.contains(e.target) && !langToggle.contains(e.target)) {
				langMenu.style.display = 'none';
			}
		});
	}
});

document.addEventListener('DOMContentLoaded', function () {
	document.body.addEventListener('click', function (e) {
		const btn = e.target.closest('.vote-btn');
		if (!btn) return;

		e.preventDefault();

		const commentId = btn.getAttribute('data-comment-id');
		const action = btn.getAttribute('data-action');

		if (!commentId || !action) return;

		btn.style.opacity = '0.5';

		const formData = new FormData();
		formData.append('comment_id', commentId);
		formData.append('action', action);

		fetch('backend/blog/comment_vote.php', {
			method: 'POST',
			body: formData
		})
			.then(response => response.json())
			.then(data => {
				btn.style.opacity = '1';

				if (data.success) {
					const container = btn.closest('.comment-actions');
					if (container) {
						const likeBtn = container.querySelector('.vote-btn[data-action="like"] .count');
						const dislikeBtn = container.querySelector('.vote-btn[data-action="dislike"] .count');

						if (likeBtn) likeBtn.textContent = data.likes;
						if (dislikeBtn) dislikeBtn.textContent = data.dislikes;

						const commentItem = btn.closest('.modern-comment-item');
						if (commentItem) {
							commentItem.setAttribute('data-likes', data.likes);
							commentItem.setAttribute('data-score', parseInt(data.likes) - parseInt(data.dislikes));
						}
					}
				} else {
					console.error('Vote failed:', data.error);
				}
			})
			.catch(error => {
				console.error('Error:', error);
				btn.style.opacity = '1';
			});
	});
});