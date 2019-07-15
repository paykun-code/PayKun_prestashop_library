

{capture name=path}
	<a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'htmlall':'UTF-8'}" rel="nofollow" title="{l s='Go back to the Checkout' mod='Paykun'}">{l s='Checkout' mod='Paykun'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Pay using Paykun' mod='Paykun'}
{/capture}



{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}
  {if isset($api_errors)}
  <div class="errors">
	{foreach $api_errors as $error}
		<div class='alert alert-danger error'>{$error}</div>
	{/foreach}
 </div>
 {/if}
<P>Selected Payment Method : <b>{$checkout_label}</b></p>

<form action="{$pk_data['gateway_url']}" method="post">
{if isset($showPhoneBox)}
	<div style="padding:20px 0px;margin:20px 0;">
		<label>Mobile No.</label>
		<input class='form-controls' type="text" name='mobile' value="{$mobile}">
		<div>
		<input class='btn btn-primary' type='submit' name='updatePhone' value='Update Phone'>
		</div>
	</div>
{else}
	<input type="hidden" name="encrypted_request" value="{$pk_data['encrypted_request']}" />
	<input type="hidden" name="merchant_id" value="{$pk_data['merchant_id']}" />
	<input type="hidden" name="access_token" value="{$pk_data['access_token']}" />


	<p class="cart_navigation" id="cart_navigation">
		<a href="{$link->getPageLink('order', true)}?step=3" class="button_large">{l s='Other payment methods' mod='Paykun'}</a>
		{if $currency_code eq 'INR'}
			<input type="submit" value="{l s='Confirm Order' mod='Paykun'}" class="exclusive_large" />

		{else}
			<strong style="color: red">PayKun can only process INR currency right now.</strong>
		{/if}
	</p>
{/if}
</form>
