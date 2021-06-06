<?php

namespace Laravel\Cashier;

use Error;
use Exception;
use Money\Money;
use Illuminate\Support\Str;
use Laravel\Cashier\Order\Order;
use Money\Currencies\ISOCurrencies;
use Laravel\Cashier\Order\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Money\Formatter\IntlMoneyFormatter;

class Cashier
{
    /**
     * The current currency.
     *
     * @var string
     */
    protected static $currency = 'eur';

    /**
     * The current currency symbol.
     *
     * @var string
     */
    protected static $currencySymbol = '€';

    /**
     * The current currency symbol.
     *
     * @var string
     */
    protected static $currencyLocale = 'de_DE';

    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected static $formatCurrencyUsing;

    /**
     * Indicates if Cashier migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    /**
     * Indicates if Cashier routes will be registered.
     *
     * @var bool
     */
    public static $registersRoutes = true;

    /**
     * The order model class name.
     *
     * @var string
     */
    public static $orderModel = Order::class;

    /**
     * The order model class name.
     *
     * @var string
     */
    public static $orderItemModel = OrderItem::class;

    /**
     * Process scheduled OrderItems
     *
     * @return \Illuminate\Support\Collection
     */
    public static function run()
    {
        $orderItem = self::$orderItemModel;
        $items = $orderItem::shouldProcess()->get();

        $orders = $items->chunkByOwnerAndCurrency()->map(function ($chunk) {
            $orderModel = self::$orderModel;
            return $orderModel::createFromItems($chunk)->processPayment();
        });

        return $orders;
    }

    /**
     * Set the default currency for this merchant.
     *
     * @param  string  $currency
     * @param  string|null  $symbol
     * @return void
     * @throws \Exception
     */
    public static function useCurrency($currency, $symbol = null)
    {
        static::$currency = $currency;

        static::useCurrencySymbol($symbol ?: static::guessCurrencySymbol($currency));
    }

    /**
     * Set the currency symbol to be used when formatting currency.
     *
     * @param  string  $symbol
     * @return void
     */
    public static function useCurrencySymbol($symbol)
    {
        static::$currencySymbol = $symbol;
    }

    /**
     * Set the currency locale to be used when formatting currency.
     *
     * @param  string  $locale
     * @return void
     */
    public static function useCurrencyLocale($locale)
    {
        static::$currencyLocale = $locale;
    }

    /**
     * Guess the currency symbol for the given currency.
     *
     * @param  string  $currency
     * @return string
     * @throws \Exception
     */
    protected static function guessCurrencySymbol($currency)
    {
        switch (strtolower($currency)) {
            case 'usd':
            case 'aud':
            case 'cad':
                return '$';
            case 'eur':
                return '€';
            case 'gbp':
                return '£';
            default:
                throw new Exception('Unable to guess symbol for currency. Please explicitly specify it.');
        }
    }

    /**
     * Get the currency currently in use.
     *
     * @return string
     */
    public static function usesCurrency()
    {
        return static::$currency;
    }

    /**
     * Get the currency symbol currently in use.
     *
     * @return string
     */
    public static function usesCurrencySymbol()
    {
        return static::$currencySymbol;
    }

    /**
     * Get the currency locale currently in use.
     *
     * @return string
     */
    public static function usesCurrencyLocale()
    {
        return static::$currencyLocale;
    }

    /**
     * Set the custom currency formatter.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function formatCurrencyUsing(callable $callback)
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param \Money\Money $money
     * @return string
     */
    public static function formatAmount(Money $money)
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $money);
        }

        $numberFormatter = new \NumberFormatter(static::$currencyLocale, \NumberFormatter::CURRENCY);
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies);

        return $moneyFormatter->format($money);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @return null|string
     */
    public static function getLocale(Model $owner)
    {
        if (method_exists($owner, 'getLocale')) {
            $locale = $owner->getLocale();

            if (!empty($locale)) {
                return $locale;
            }
        }

        return config('cashier.locale');
    }

    /**
     * Get the webhook relative url.
     *
     * @return string
     */
    public static function webhookUrl()
    {
        return self::pathFromUrl(config('cashier.webhook_url'));
    }

    /**
     * Get the first payment webhook relative url.
     *
     * @return string
     */
    public static function firstPaymentWebhookUrl()
    {
        return self::pathFromUrl(config('cashier.first_payment.webhook_url'));
    }

    /**
     * Configure Cashier to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static;
    }

    /**
     * Configure Cashier to not register its routes.
     *
     * @return static
     */
    public static function ignoreRoutes()
    {
        static::$registersRoutes = false;

        return new static;
    }

    /**
     * Get path from url.
     *
     * @param string $url
     * @return string
     */
    protected static function pathFromUrl($url)
    {
        $url_parts = parse_url($url);

        return preg_replace('/^\//', '', $url_parts['path']);
    }

    public static function setOrderModel2($test)
    {
        self::$orderModel = $test;
    }

    public static function __callStatic($name, $arguments)
    {
        /**
         * This will make it possible to overwride the model classes that are provided
         * in the Cachier class. With this functionality it will be possible to set
         * self::$orderModel by calling Cashier::setOrderModel(Path\To\Class:class);
         */
        if (Str::startsWith($name, 'set')) {
            $possible_model_name = (string) Str::of($name)->replace('set', '')->camel();
            if (isset(self::$$possible_model_name)) {
                self::$$possible_model_name = $arguments[0];
                return true;
            }
        }
        $class = get_called_class();
        throw new Error("Call to undefined method {$class}::{$name}()", 1);
    }
}
