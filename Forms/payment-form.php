<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Worldpay_PaymentForm
{
	public static function render_payment_form( $storeTokens, $cardDetails ) {
		self::payment_errors_section();
		if ( null != $cardDetails ) {
			self::existing_details_script();
			self::existing_details_fields( $cardDetails );
		} else {
			self::no_existing_details_script();
		}
		self::common_fields();
		if ( $storeTokens ) {
			self::store_tokens_options();
		}
	}

	public static function payment_errors_section() {
		?>
			<div id="worldpay-payment-errors"></div>
		<?php
	}

	public static function existing_details_fields( Worldpay_CardDetails $card_details ) {
		?>
			<p class="form-row form-row-first">
				<label for="worldpay_saved_card">
					<strong>
						Saved card details:
					</strong>
				</label>
				<input id="worldpay_saved_card" type="text" class="input-text" disabled autocomplete="off" value="<?php echo $card_details->masked_card_number ?>"/>
				<input id="worldpay_token" type="hidden" data-worldpay="token" value="<?php echo $card_details->token ?>"/>
			</p>
			<p class="form-row form-row-last">
				<label for="worldpay_use_saved_card_details">
					Use saved card details?
				</label>
				<input id="worldpay_use_saved_card_details" type="checkbox" name="worldpay_use_saved_card_details" checked/>
			<p class="form-row worldpay_new_card_fields form-row-wide">
				<strong>
					New card details:
				</strong>
			</p>
		<?php
	}

	public static function common_fields() {
		?>
			<p class="form-row form-row-wide worldpay_new_card_fields validate-required">
				<label for="worldpay_name">
					Name on Card
					<abbr class="required" title="required">*</abbr>
				</label>
				<input id="worldpay_name" data-worldpay="name" name="name" type="text" placeholder="Name on Card" class="input-text" autocomplete="off"
			</p>
			<p class="form-row form-row-wide worldpay_new_card_fields validate-required">
				<label for="worldpay_card_number">
					Card Number
					<abbr class="required" title="required">*</abbr>
				</label>
				<input id="worldpay_card_number" data-worldpay="number" size="20" type="text" placeholder="Card Number" class="input-text" autocomplete="off"/>
			</p>
			<p class="form-row form-row-wide validate-required">
				<label for="worldpay_cvc">
					CVC
					<abbr class="required" title="required">*</abbr>
				</label>
				<span class="help">The 3 or 4 numbers on the back of your card</span>
				<input id="worldpay_cvc" data-worldpay="cvc" size="4" type="text" placeholder="CVC" class="input-text" autocomplete="off"/>
			</p>
			<p class="form-row form-row-wide worldpay_new_card_fields validate-required">
				<label for="worldpay_expiration_month">
					Expiration Date
					<abbr class="required" title="required">*</abbr>
				</label>
				<?php self::month_select() ?>
				<?php self::year_select() ?>
			</p>
		<?php
	}

	public static function month_select() {
		?>
			<select id="worldpay_expiration_month" data-worldpay="exp-month">
				<option value="">Month</option>
				<?php for($i = 1; $i <=12; $i++) { ?>
					<?php $formatted = sprintf("%02d",$i); ?>
					<option value="<?php echo $formatted ?>"><?php echo $formatted ?> - <?= date("F", mktime(0, 0, 0, $i, 10)) ?></option>
				<?php } ?>
			</select>
		<?php
	}

	public static function year_select() {
		?>
			<select id="worldpay_expiration_year" data-worldpay="exp-year">
				<option value="">Year</option>
				<?php
				$year = date("Y");
				for($i = $year; $i <= $year + 13; $i++) { ?>
					<option value="<?php echo $i ?>"><?php echo $i ?></option>
				<?php } ?>
			</select>
		<?php
	}

	public static function store_tokens_options() {
		?>
		<p class="form-row form-row-wide worldpay_new_card_fields">
			<label for="worldpay_save_card_details">
				<?php echo __('Save card details?') ?>
			</label>
			<input id="worldpay_save_card_details" type="checkbox" name="worldpay_save_card_details" checked/>
		</p>
	<?php
	}

	public static function no_existing_details_script() {
		?>
			<script type="text/javascript">
				WorldpayCheckout.setupNewCardForm();
			</script>
		<?php
	}

	public static function existing_details_script() {
		?>
			<script type="text/javascript">
				jQuery(function($){
					$(document).ready(function(){
						var checkbox = document.getElementsByName('worldpay_use_saved_card_details')[0];
						var newCardFormSections = $('.worldpay_new_card_fields');
						if(checkbox != null)
						{
							newCardFormSections.hide();
							$(checkbox).click(function()
							{
								newCardFormSections.toggle();
								if($(checkbox).attr('checked'))
								{
									WorldpayCheckout.setupReusableCardForm();
								} else {
									WorldpayCheckout.setupNewCardForm();
								}
							});
						}
						WorldpayCheckout.setupReusableCardForm();
					});
				});
			</script>
		<?php
	}
}
