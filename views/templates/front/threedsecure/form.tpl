<!doctype html>
<html>
    <head>
        <title>{l s='Processing Secure Payment' mod='mastercard'}</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <meta name="description" content="{l s='Processing Secure Payment' mod='mastercard'}" />
        <meta name="robots" content="noindex" />
        <style type="text/css">
            {literal}
            body {font-family:"Trebuchet MS",sans-serif; background-color: #FFFFFF; }#msg {border:5px solid #666; background-color:#fff; margin:20px; padding:25px; max-width:40em; -webkit-border-radius: 10px; -khtml-border-radius: 10px; -moz-border-radius: 10px; border-radius: 10px;}#submitButton { text-align: center ; }#footnote {font-size:0.8em;}
            {/literal}
        </style>
    </head>
    {if !$authenticationRedirect.acsUrl || !$authenticationRedirect.paReq}
    <body>
        <p>Data Error</p>
    </body>
    {else}
    <body onload="return window.document.echoForm.submit()">
        <form name="echoForm" method="post" action="{$authenticationRedirect.acsUrl}" accept-charset="UTF-8" id="echoForm">
            <input type="hidden" name="PaReq" value="{$authenticationRedirect.paReq}" />
            <input type="hidden" name="TermUrl" value="{$returnUrl}" />
            <input type="hidden" name="MD" value="" />
            <noscript>
                <div id="msg">
                    <div id="submitButton">
                        <input type="submit" value="{l s='Click here to continue' mod='mastercard'}" class="button" />
                    </div>
                </div>
            </noscript>
        </form>
    </body>
    {/if}
</html>
