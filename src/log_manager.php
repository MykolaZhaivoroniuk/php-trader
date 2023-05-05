<?
	/**
	 * This file is par of LogManager
	 * (c) Freelancer8899
	 */

	use Josantonius\Json\Json;

	class LogManager {
		private $trading_data_flename; /* path to trading(buy) data file */
		private $history_data_flename; /* path to history file */

		/**
		 * construct of the class
		 *
		 * @param [string] $trading_data_flename: path to trading(buy) data file
		 * @param [string] $history_data_flename: path to history file
		 */
		public function __construct($trading_data_flename, $history_data_flename) {
			$this->trading_data_flename = $trading_data_flename;
			$this->history_data_flename = $history_data_flename;
		}

		/**
		 * save the buy order
		 *
		 * @param [type] $order_id: order_id of the buy
		 * @param [type] $buy_price: real buy price
		 * @param [type] $sel_price: limit sell
		 * @param [type] $amount: amount of the buy ordr
		 * @param [type] $quantity: quantity of the buy order
		 * @return void
		 */
		public function add_trading($order_id, $buy_price, $sel_price, $amount, $quantity) {
			$json = new Json($this->trading_data_flename);
	
			$new_trading = [
				'order_id' => $order_id,
				'buy_price' => $buy_price,
				'sel_price' => $sel_price,
				'USDT_ammount' => $amount,
				'quantity' => $quantity,
				'buy_date' => date('Y-m-d h:i:s'),
			];
			$json->push($new_trading);
			$this->add_history('buy', $order_id, $buy_price, '', $amount, '', $quantity, date('Y-m-d h:i:s'), '');
		} 

		/**
		 * save trading history whenever buy or sell.
		 *
		 * @param [type] $type: buy or sell
		 * @param [type] $order_id: buy order id
		 * @param [type] $buy_price: real buy price
		 * @param [type] $sel_price: real sell price
		 * @param [type] $amount: buy or sell amount
		 * @param [type] $profit: revenue of the trading
		 * @param [type] $quantity: quantity of buy or sell
		 * @param [type] $buy_date: buy date
		 * @param [type] $sale_date: sell date
		 * @return void
		 */
		public function add_history($type, $order_id, $buy_price, $sel_price, $amount, $profit, $quantity, $buy_date, $sale_date=null) {
			$json = new Json($this->history_data_flename);
			$new_history = [
				'type' => $type,
				'order_id' => $order_id,
				'buy_price' => $buy_price,
				'sel_price' => $sel_price,
				'USDT_ammount' => $amount,
				'profit' => $profit,
				'quantity' => $quantity,
				'buy_date' => $buy_date,
				'sale_date' => $sale_date == null ? date('Y-m-d h:i:s') : $sale_date,
			];
			$json->push($new_history);
		}

		/**
		 * read trading data for sell
		 *
		 * @param [type] $price: current coin price
		 * @return array: all of the trading data
		 */
		public function read_trades($price) {
			$json = new Json($this->trading_data_flename);
			$trade_data = $json->get();
			// if(isset($trade_data['data'])) $trade_data = $trade_data['data'];
			if(!is_array($trade_data)) $trade_data = [];
			
			foreach ($trade_data as $index => $trade_row) {
				$trade_data[$index]['is_sale_opend'] = $trade_row['sel_price'] >= $price;
			}
			return $trade_data;
		}

		/**
		 * remove trade data item because the trade is closed.
		 *
		 * @param [type] $index: index to remove
		 * @return void
		 */
		public function remove_trade_data($index) {
			$json = new Json($this->trading_data_flename);
			$json->unset($index, true);
		}
	}
?>