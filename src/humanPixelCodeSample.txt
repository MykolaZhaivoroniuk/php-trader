<?
	/**
	 * This file is main routin of PHP Trading bot using binance API.
	 * (c) Freelancer8899
	 */

	require 'vendor/autoload.php';
	require 'src/log_manager.php';

	use Josantonius\Json\Json;

	$trading_pair = 'BTCUSDT';
	$trading_coin_name = 'BTC';
	$total_quota = 1000000;
	$trading_pair_amount = 300;
	$tradeing_limit_coin = 30;
	$sleep_time = 1; //30

	$json = new Json('setting.json');
	$setting = $json->get(); 	/* should be sort grade_grade */

	class Trader {
		private $api; /* binance api */
		private $log_manager; /* logo manager for save trading data and history */
		private $g_price; /* current price */
		private $price_level; /* current price level */
		private $trade_grid; /* trading data for buy and sell */

		/**
		 * construct of the class.
		 */
		function __construct() {
			global $setting;

			echo "Starting trading bot ...".PHP_EOL;
			$this->api = new Binance\API( 'config.json' );
			$this->api = new Binance\RateLimiter($this->api);
			$this->api->useServerTime();
			$this->log_manager = new LogManager('logo/trading_data.json', 'logo/history_data.json');
			$this->trade_grid = $setting['trade_grid'];
			echo "Successfully started trading bot.".PHP_EOL;
		}

		/**
		 * main function to start
		 * 
		 * @return void
		 */
		public function runTrade() {
			global $trading_pair;
			global $sleep_time;

			while(true) {
				$this->check_balance();
				$this->g_price = $this->api->price($trading_pair);
				$this->price_level = $this->get_price_level($this->trade_grid, $this->g_price);
				$trade_data = $this->log_manager->read_trades($this->g_price); /* read trading data from logo file */
				echo "Price of BTC: {$this->g_price}$.".PHP_EOL;
		
				$this->sell_process($trade_data);
				$this->buy_process();

				sleep($sleep_time);
			}
		}

		/**
		 * Check the trading data and sell if there are opened sell orders.
		 *
		 * @param [type] $trade_data: saved trading data whenever buy order.
		 * @return void
		 */
		public function sell_process($trade_data) {
			$sell_order_count = 0;
			foreach ($trade_data as $index => $trade_item) {
				if(isset($trade_item['is_sell_opend']) && $trade_item['is_sell_opend']) {
					$sell_order_count += $this->do_sell_order($index, $sell_order_count, $trade_item);
				}
			}
		}
	
		/**
		 * Check the trading grid and buy if there is opened buy order.
		 *
		 * @return void
		 */
		public function buy_process() {
			foreach($this->trade_grid as $index => $trade_grid_item) {
				if(isset($trade_grid_item['is_place_buy']) && $trade_grid_item['is_place_buy']) {
					if($index == $this->price_level) {
						continue;
					} else if($index < $this->price_level) { /* in case of the price fall down */
						$this->do_buy_order($this->price_level - 1, $this->g_price);
						$this->trade_grid[$index]['is_place_buy'] = false;
					} else {
						$this->trade_grid[$index]['is_place_buy'] = false;
					}
				}
			}
	
			if($this->price_level >= 0 && $this->price_level < count($this->trade_grid)) {
				$this->trade_grid[$this->price_level]['is_place_buy'] = true;
			}
		}
	
		/**
		 * run market sell order.
		 * use market order because the logic of limit order is implemented at main routin and sell_process function.
		 * the market order is used for clear history management
		 *
		 * @param [int] $index: index of trading data logo. this is used to remove trading data logo after sell.
		 * @param [int] $sell_order_count: count of sell order in current process. this is used to indicate the real position to remove at the trade_data.json.
		 * @param [array] $trade_item: detail data of buy order.
		 * @return bool: true or false
		 */
		public function do_sell_order($index, $sell_order_count, $trade_item) {
			global $trading_pair;
			global $trading_coin_name;

			$quantity = $trade_item['quantity'];
			if(!$this->enough_balance($trading_coin_name, $quantity) || !$this->check_total_quota($trading_coin_name)) return false;
			$sell_order = $this->api->marketSell($trading_pair, $quantity);

			/* ^ Save history */
			$buy_amount = $trade_item['USDT_ammount'];
			$sell_amount = $sell_order['cummulativeQuoteQty'];
			$profit = ($sell_amount - $buy_amount) * 0.999 * 0.999; /* double multiple for buy and sell */
			$this->log_manager->add_history('sell', $trade_item['order_id'], $trade_item['buy_price'], $this->g_price, $sell_amount, $profit, $quantity, $trade_item['buy_date']);
			/* ~ Save history */

			/* ^ Remove trading data */
			$real_position = $index - $sell_order_count;
			$this->log_manager->remove_trade_data($real_position);
			/* ~ Remove trading data */

			echo "Sell: $trading_pair, price: ".$this->g_price.",  quantity: $quantity, amount: $sell_amount, profit: $profit" . PHP_EOL;
			return true;
		}
	
		/**
		 * run market buy order
		 * use market order because the logic of limit order is implemented at buy_process function.
		 * the market order is used for clear history management
		 * 
		 * @param [type] $buy_level
		 * @param [type] $g_price
		 * @return void
		 */
		public function do_buy_order($buy_level, $g_price) {
			global $trading_pair;
			global $trading_pair_amount;
			$quantity = round($trading_pair_amount / $g_price, 6);

			if(!$this->enough_balance('USDT', $trading_pair_amount)) return;

			$buy_order = $this->api->marketBuy($trading_pair, $quantity);
			$amount = $buy_order['cummulativeQuoteQty'];

			/* ^ register trading data */
			$this->log_manager->add_trading($buy_order['orderId'], $g_price, $this->trade_grid[$buy_level]['sell'], $amount, $quantity);
			/* ~ register trading data */

			echo "Buy: $trading_pair, price: ".$this->g_price.", quantity: $quantity, amount: $amount" . PHP_EOL;
		}
	
		/**
		 * Get trading grid level of the current price.
		 *
		 * @param [type] $trade_grid: trading grid for buy or sell
		 * @param [type] $g_price: current price of the coin
		 * @return int
		 */
		public function get_price_level($trade_grid, $g_price) {
			$price_level = -1;
			foreach($trade_grid as $index => $trade_grid_item) {
				if($g_price >= $trade_grid_item['buy'] && $g_price < $trade_grid_item['sell']) {
					$price_level = $index;
					break;
				}
			}
	
			/* should be process in case of out of level */
			return $price_level;
		}

		/**
		 * check the balance of the coin is enough for buy or sell.
		 *
		 * @param [String] $symbol: symbol of the coin. BTC, USDT, BNT, ...
		 * @param [Numeric] $quantity: quantity for check.
		 * @return bool: true if there is enough quantity, false otherwise
		 */
		public function enough_balance($symbol, $quantity) {
			$balances = $this->check_balance();
			return $balances[$symbol]['available'] >= $quantity;
		}

		/**
		 * check total quota
		 *
		 * @param [type] $symbol: coin name
		 * @return bool: true if available for buy, false otherwise.
		 */
		public function check_total_quota($symbol) {
			global $total_quota;

			$balance = $this->check_balance($symbol);
			return $balance * $this->g_price < $total_quota;
		}

		/**
		 * check and display balance
		 *
		 * @return mixed
		 */
		public function check_balance($symbol = '', $display = false) {
			$ticker = $this->api->prices();
			$balances = $this->api->balances($ticker);
			if($symbol != '') $balances = $balances[$symbol]['available'];
			if($display) print_r($balances);
			return $balances;
		}
	}

	$trader = new Trader();	
	$trader->runTrade();
?>