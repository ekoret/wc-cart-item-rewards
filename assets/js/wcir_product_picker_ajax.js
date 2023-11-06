(function ($) {
	$(function () {
		$('#wcir_product_id_select').selectWoo({
			ajax: {
				url: ajaxurl,
				data: function (params) {
					return {
						term: params.term,
						action: 'woocommerce_json_search_products_and_variations',
						security: $(this).data('security'), // Use data() to access data attributes
					};
				},
				processResults: function (data) {
					var terms = [];
					if (data) {
						$.each(data, function (id, text) {
							terms.push({ id: id, text: text });
						});
					}
					return { results: terms };
				},
				cache: true,
			},
		});

		$('#wcir_form').on('submit', function (event) {
			// Get the selected value from the select element
			var selectedProductId = $('#wcir_product_id_select').val();

			// Include the selected value in the form data
			$(this).append(
				'<input type="hidden" name="wcir_product_id" value="' +
					selectedProductId +
					'">'
			);
		});
	});
})(jQuery);
