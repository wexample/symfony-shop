## Architecture

src/Entity/Cart.php and src/Entity/CartItem.php use the symfony-money priced traits; an item keeps the product's price in `priceBase` and derives its effective prices from its variations. src/Entity/ProductAvailability.php rows are never deleted: what is left is the quantity minus confirmed and unexpired reservations (src/Entity/StockReservation.php). src/Service/StockService.php reserves inside a transaction with the availability row locked, and checks everything before persisting anything, so a refused reservation leaves the entity manager usable.

src/EventSubscriber/CartPaymentSubscriber.php is the only link with payments: the cart is a `PayableInterface`, the payment knows it by type and id, and a payment replaced by another never reopens the cart.
