(function($) {
    var cookie = {
        set: function (name, value, stringifyObject) {
            if (value && stringifyObject) value = JSON.stringify(value);

            var date = new Date();
            date.setTime(date.getTime() + 24 * 60 * 60 * 1000);

            document.cookie = name + '=' + (value || '')  + '; expires=' + date.toUTCString() + '; path=/';
        },
        remove: function (name) {
            document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
        }
    };


    var messages = {
        ru: { cartIsEmpty: 'Корзина пуста' },
        en: { cartIsEmpty: 'Cart is empty' },
        zh: { cartIsEmpty: '' }
    };


    var WIDGET_DOM_ID = 'dd-widget';


    var widget;


    window.DDeliveryWidgetInit = function () {
        var $ddeliveryLabel = $('label[for="ddelivery.ddelivery"]');

        // Если место, где должен отображаться виджет, на странице не существует или в текущий момент
        // не отображается, функцию выполнять не нужно
        if (!$ddeliveryLabel.length || $ddeliveryLabel.is(':hidden')) return;

        // Удаление из Cookies старых данных при первой загрузке скрипта
        // (при перезапуске виджета очищать Cookies нельзя, т.к. Simple перезагружает блоки на стадии
        // оформления заказа и очистка Cookies помешает сохранить в базе CMS данные виджета и DDelivery ID заказа)
        if (!widget) {
            cookie.remove('DDWidgetData');
            cookie.remove('DDOrderData');
        }

        // Элементы поля валидации widget_validation
        var $validationLabel = $('.row-shipping_widget_validation label'),
            $validationInput = $('.row-shipping_widget_validation input');

        // Скрываем поле валидации, оставляя на экране только вывод ошибки
        $validationLabel.remove();
        $validationInput.hide();

        // Если в поле валидации уже есть значение, его нужно удалить
        $validationInput.val('');

        var baseHref = $(document).find('base[href]').attr('href') || '/';

        // Получение настроек сайта / модуля
        $.get(baseHref + 'index.php?route=module/ddelivery/get_settings', function (settings) {
            var lang = 'ru';
            switch (settings.lang) {
                case 'en':    lang = 'en'; break;
                case 'zh-CN': lang = 'zh'; break;
            }

            // Если произошел повторный вызов функции, старый виджет нужно уничтожить
            if (widget) widget.destruct();

            // DOM-узел для монтирования виджета
            $ddeliveryLabel.after('<div id="' + WIDGET_DOM_ID + '"></div>');

            // Получение данных корзины (список товаров, габариты)
            $.get(baseHref + 'index.php?route=module/ddelivery/get_cart', function (cart) {
                // Корзина пуста, виджет запустить нельзя
                if (!cart.products || !cart.products.length)
                    return alert(messages[lang].cartIsEmpty);

                // Инициализация виджета DDelivery
                widget = new DDeliveryWidgetCart(WIDGET_DOM_ID, {
                    lang: lang,
                    apiScript: baseHref + 'index.php?route=module/ddelivery/widget_api',

                    products: cart.products,
                    weight: cart.weight
                });

                // Изменение значений в виджете
                widget.on('change', function (values) {
                    // Сохранение данных виджета в Cookies
                    cookie.set('DDWidgetData', values, true);
                });

                // Заказ передан на сервер DDelivery
                widget.on('afterSubmit', function (response) {
                    if (response.status === 'ok') {
                        // Чтобы пройти валидацию поля widget_validation
                        $validationInput.val(1);

                        // Сохранение данных заказа в Cookies
                        cookie.set('DDOrderData', {
                            id: response.id,
                            confirmed: response.confirmed
                        }, true);

                        var $buttonBlock = $('.simplecheckout-button-block'),
                            $nextStepBtn = $buttonBlock.find('.button[data-onclick=nextStep]:visible'),
                            $confirmBtn  = $buttonBlock.find('#button-confirm:visible, #simplecheckout_button_confirm:visible');

                        // Переход к следующему шагу
                        if ($nextStepBtn.length)
                            $nextStepBtn.trigger('click');
                        // Либо подтверждение заказа
                        else if ($confirmBtn.length)
                            $confirmBtn.trigger('click');
                    } else {
                        console.error(response.message);
                    }
                });

                // Вывод ошибок виджета в консоль
                widget.on('error', function (e) { console.error(e); });
            });
        });
    }
})(jQuery || $);