<?php
/**
 * Review order table - CUSTOM per FP Experiences
 *
 * Template override per gestire correttamente gift vouchers e prenotazioni
 * normali nel checkout, evitando link al prodotto virtuale placeholder.
 *
 * @package FP_Experiences
 * @version 1.2.0
 */

defined('ABSPATH') || exit;
?>

<table class="shop_table woocommerce-checkout-review-order-table">
	<thead>
		<tr>
			<th class="product-name"><?php esc_html_e('Product', 'woocommerce'); ?></th>
			<th class="product-total"><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		do_action('woocommerce_review_order_before_cart_contents');

		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			$_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
			
			if (!$_product || !$_product->exists() || 0 === $cart_item['quantity'] || !apply_filters('woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key)) {
				continue;
			}
			
			$is_gift = ($cart_item['_fp_exp_item_type'] ?? '') === 'gift';
			$is_experience = !empty($cart_item['fp_exp_item']);
			?>
			<tr class="<?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">
				<td class="product-name">
					<?php 
					if ($is_gift) {
						echo '<strong>' . esc_html($cart_item['experience_title'] ?? 'Gift Voucher') . '</strong>';
						echo '<br><small style="color: #666;">' . esc_html__('Gift Voucher', 'fp-experiences') . '</small>';
						
						$session_data = WC()->session ? WC()->session->get('fp_exp_gift_pending') : null;
						if ($session_data && !empty($session_data['recipient']['name'])) {
							echo '<br><small style="color: #999;">';
							echo sprintf(
								esc_html__('For: %s', 'fp-experiences'),
								esc_html($session_data['recipient']['name'])
							);
							echo '</small>';
						}
					} else {
						echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key));
					}
					
					echo '&nbsp;';
					echo apply_filters(
						'woocommerce_checkout_cart_item_quantity',
						' <strong class="product-quantity">' . sprintf('&times;&nbsp;%s', $cart_item['quantity']) . '</strong>',
						$cart_item,
						$cart_item_key
					);
					
					if (!$is_gift && !$is_experience) {
						echo wc_get_formatted_cart_item_data($cart_item);
					}
					
					do_action('woocommerce_after_cart_item_name', $cart_item, $cart_item_key);
					?>
				</td>
				<td class="product-total">
					<?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); ?>
				</td>
			</tr>
			<?php
		}

		do_action('woocommerce_review_order_after_cart_contents');
		?>
	</tbody>
	<tfoot>
		<tr class="cart-subtotal">
			<th><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
			<td><?php wc_cart_totals_subtotal_html(); ?></td>
		</tr>

		<?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr(sanitize_title($code)); ?>">
				<th><?php wc_cart_totals_coupon_label($coupon); ?></th>
				<td><?php wc_cart_totals_coupon_html($coupon); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>

			<?php do_action('woocommerce_review_order_before_shipping'); ?>

			<?php wc_cart_totals_shipping_html(); ?>

			<?php do_action('woocommerce_review_order_after_shipping'); ?>

		<?php endif; ?>

		<?php foreach (WC()->cart->get_fees() as $fee) : ?>
			<tr class="fee">
				<th><?php echo esc_html($fee->name); ?></th>
				<td><?php wc_cart_totals_fee_html($fee); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) : ?>
			<?php if ('itemized' === get_option('woocommerce_tax_total_display')) : ?>
				<?php foreach (WC()->cart->get_tax_totals() as $code => $tax) : ?>
					<tr class="tax-rate tax-rate-<?php echo esc_attr(sanitize_title($code)); ?>">
						<th><?php echo esc_html($tax->label); ?></th>
						<td><?php echo wp_kses_post($tax->formatted_amount); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="tax-total">
					<th><?php echo esc_html(WC()->countries->tax_or_vat()); ?></th>
					<td><?php wc_cart_totals_taxes_total_html(); ?></td>
				</tr>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action('woocommerce_review_order_before_order_total'); ?>

		<tr class="order-total">
			<th><?php esc_html_e('Total', 'woocommerce'); ?></th>
			<td><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>

		<?php do_action('woocommerce_review_order_after_order_total'); ?>
	</tfoot>
</table>
