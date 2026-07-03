{*
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2014 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{assign var="rechnung" value="yes"}
<div style="font-size: 8pt; color: #222">
<table>
	<tr><td><br>Biberist, {$smarty.now|date_format:"%d.%m.%Y"}</td></tr>
</table>

<!-- ADDRESSES -->

				<table style="width: 100%">					<tr>

						<td style="width: 55%">
							
						</td>
						<td style="width: 45%">
							<div style="font-size: 7pt; ">Spielezar AG - {$shop_name|escape:'html':'UTF-8'} - Biberiststrasse 4 - 4563 Gerlafingen<hr></div>
                            <div style="line-height: 0.5pt">&nbsp;</div>
						</td>
					</tr>
					<tr>

						<td style="width: 55%">
							
						</td>
						<td style="width: 45%">
							<span style="font-size: 10pt">{if str_contains($invoice_address,"Ladenkunde")}{else}{$invoice_address}{/if}</span>
						</td>
					</tr>
				</table>

<!-- / ADDRESSES -->

<div style="line-height: 48px">&nbsp;</div>



<div style="width: 100%;"><h3>1. Mahnung - Bestellung vom {dateFormat date=$order->date_add full=0}, Auftrag Nr. {$order->getUniqReference()}</h3></div>

<div style="width: 100%; font-size: 10pt; line-height: 17px;"><b>Sehr geehrte(r) {$firstname} {$lastname}</b></div>

<div style="width: 100%; font-size: 10pt; line-height: 17px;">Wir durften Ihnen am {$versanddatum|date_format:"%d.%m.%Y"} Artikel gemäss Rechnungskopie liefern. Leider ist diese Rechnung noch offen.</div>

<div style="width: 100%; font-size: 10pt; line-height: 17px;">
	{if $erinnerungdatum}
		<span>Bereits am {$erinnerungdatum|date_format:"%d.%m.%Y"} haben wir Sie per E-Mail auf den offenen Betrag aufmerksam gemacht.</span>
	{/if}
	<span>Als kleines Familienunternehmen sind wir auf die Bezahlung sehr angewiesen. Um unnötige Aufwände und Zusatzkosten zu vermeiden, bitten wir Sie, <b>den Betrag von {displayPrice currency=$order->id_currency price=$order_invoice->total_paid_tax_incl} bis spätestens {"+14 days"|date_format:"%d.%m.%Y"} einzuzahlen.</b>
		Sollte sich Ihre Zahlung mit diesem Schreiben kreuzen, ist die Mahnung hinfällig. Falls Ihre Überweisung bereits vor einiger Zeit erledigt wurde, bitten wir Sie um die entsprechenden Zahlinformationen (Datum und Betrag).</span>
</div>

<p></p>
<p></p>



<table>
	<tr>
    	<td style="width: 70%"></td>
        <td style="font-size: 10pt">Mit freundlichen Grüssen<br /><br />Ihr {$shop_name|escape:'html':'UTF-8'} Team</td>
    </tr>
</table>

				<table style="width: 40%; ">
					<tr>
						<td>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
                        <p></p>
							<span style="font-size: 10pt;" ><b>Beilage:</b><br>Rechnungskopie inkl. QR-Einzahlungsschein</span>

							 
						</td>
					</tr>
				</table>


</div>
