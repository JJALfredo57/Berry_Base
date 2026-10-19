# Berry Base account required ordering manuscript revisions

These replacement passages describe the account-required ordering revision. Historical questionnaire responses and evaluation scores remain records of the version originally evaluated. New screenshots and evaluation results must reflect an actual test of this revision.

## Chapter I Background of the Study

Berry Base serves as a centralized web-based platform for local cake shops and customers in Bautista, Pangasinan. Guests may browse shops and cake catalogs, search and filter products, and view prices and published reviews. Customers must register and sign in before adding items to their account cart, submitting ready-made or customized cake requests, or placing orders. When a guest initiates a purchase, the platform temporarily retains the selected product and redirects the visitor to account registration or login. After successful authentication, the customer continues the selected action without having to select the product again. An order is created only after the customer reviews the checkout details and explicitly submits the order.

## Chapter I Statement of the Objectives

To design and develop a system that allows local cake shop owners and sellers to register their shops, manage products, configure delivery zones, process ready-made and custom cake orders, communicate with customers, assign riders, manage reviews, and monitor payouts. The system allows guests to browse shops and product catalogs, while registered and authenticated customers can manage carts, submit requests, place orders, pay deposits or remaining balances through supported methods, track orders, submit reviews, and receive notifications. Product selections are retained temporarily during authentication to support continuity between browsing and checkout. Customer verification, promo codes, discount vouchers, loyalty rewards, and membership points support eligible customer benefits and promotional transactions.

## Chapter I Scope and Delimitation

Guests have browsing access to shop profiles, product catalogs, product information, prices, search and filtering tools, and published reviews. Selecting Add to Cart, Buy Now, or Request Custom Cake requires customer authentication before the action is completed. The platform retains the pending product selection in the current browser session for up to thirty minutes. The selection does not create an order, reserve stock, or create a custom cake capacity hold.

Existing customers log in to continue their selected action. New customers complete the registration and account verification process and are then signed in automatically. An Add to Cart action saves the item to the authenticated customer's cart. A Buy Now action opens checkout review for the selected item. A custom cake action opens the selected verified shop's custom request form. Login and registration do not automatically submit an order. The customer must review the current product price, quantity, schedule, fulfillment details, and payment method and explicitly select Place Order.

The system revalidates availability, stock, applicable discounts, delivery coverage, schedules, and capacity when processing the transaction. A selection retained during authentication does not guarantee its earlier price or availability. Account carts belong to the authenticated customer; purchases are not assigned to an account solely because a phone number matches.

Historical guest orders created before this revision retain their existing tracking, payment, receipt, and refund support. This compatibility provision does not permit new guest orders. Private information and actions for customer-owned orders require the owning customer's authenticated account. Payment return processing continues to verify gateway results before requiring customer sign-in to access private order information.

## Chapter I Paradigm of the Study

For the input description, replace the guest checkout requirement with account-required ordering, temporary retention of purchase selections during authentication, customer registration with email or SMS account verification, and customer-owned cart and checkout functions. Account verification OTP is distinct from the discontinued guest checkout OTP workflow.

For the process description, include guest browsing, purchase selection capture, customer authentication, selection restoration, checkout review, server-side transaction validation, explicit order submission, payment processing, and fulfillment updates.

For the output description, state that the completed revision supports browsing guests and authenticated customer ordering. Retain the existing seller, rider, and super admin functions described in the manuscript.

## Chapter I Definition of Terms

**Guest User.** A visitor who has not signed in to a customer account and may browse shops, catalogs, product details, prices, and published reviews. A guest must register or log in before completing cart, request, or order actions. Access to historical guest orders is maintained only for compatibility with records created before the account-required ordering revision.

**Registered Customer.** A user who has completed account registration and verification. After signing in, the customer can manage a personal cart, checkout, orders, requests, addresses, messages, reviews, rewards, vouchers, and verification status according to the platform's eligibility rules.

**Retained Purchase Selection.** A temporary record in the current browser session of the product, quantity, size, personalization, or shop action selected before authentication. It expires after thirty minutes, creates no order or reservation, and is revalidated after sign-in.

**Checkout.** The authenticated customer process of reviewing selected products and current prices, providing fulfillment and scheduling details, selecting eligible promotions and payment options, and explicitly submitting an order. Completing authentication alone does not place an order.

**Custom Cake Request.** A request submitted by an authenticated customer to a verified seller using the custom cake form. Selecting this action as a guest leads first to authentication and then to the selected shop's form. Seller review, pricing, customer acceptance, and payment continue through the existing custom order workflow.

## Chapter III Logical Design and Use Case Explanation

The Guest actor can Browse Shops, Browse Product Catalog, Search and Filter Products, View Product Details, and View Published Reviews. When the Guest selects an ordering action, the system directs the visitor to Register Account or Log In and retains the selected product or shop action temporarily.

The Registered Customer actor completes authentication before Add to Cart, Checkout and Payment, Submit Ready-Made Request, Submit Custom Order Request, Track Customer Orders, Message Seller, and Leave Review. A retained Buy Now selection resumes at checkout review. A retained Add to Cart selection is saved to the customer's cart. A retained custom cake action resumes at the selected verified shop's form. Request-to-bake actions return the customer to the shop to review and submit the request. Order creation follows the customer's explicit Place Order submission and successful validation.

The Seller, Rider, and Super Admin actors retain their existing responsibilities. Update the use case diagram to show the authentication prerequisite for customer transaction functions and remove new guest checkout or guest order submission use cases. Historical guest order compatibility should be documented separately from the new purchase flow.

## Chapter III System Design Table

| Module | Revised description |
| --- | --- |
| Guest Catalog and Shop Discovery | Allows visitors to browse shops, catalogs, prices, product details, and published reviews. Ordering actions direct guests to registration or login. |
| Customer Authentication and Purchase Continuation | Registers and verifies new customers, authenticates existing customers, and restores a valid pending product or shop selection. |
| Customer Cart and Checkout | Saves cart items under the signed-in customer account, presents checkout details for review, revalidates transaction requirements, and creates an order only after explicit submission. |
| Custom Cake Requests | Requires customer authentication before opening and submitting the selected verified shop's request form. |
| Historical Guest Order Support | Preserves existing tracking, payment, receipt, and refund access for guest orders created before the revision. It does not accept new guest orders. |

## Appendix M User Manual Changes

Replace the Guest Checkout page description with Account Required Checkout Entry. Update affected screenshots after browser verification.

1. Browse the catalog or a shop without signing in.
2. Select a cake, size, and quantity, then select Buy Now or Add to Cart.
3. Log in, or select Create Customer Account and complete registration and account verification.
4. After sign-in, review the restored selection in checkout or the item saved to the customer cart.
5. Review the current price and availability. Complete the required fulfillment, schedule, address, promotion, and payment details.
6. Select Place Order. Follow the existing deposit or payment instructions shown for the order.
7. For customized cakes, select Request Custom Cake, complete authentication, and fill in the selected verified shop's custom cake form before submitting the request.

If a selection expires or becomes unavailable during authentication, select the product again or adjust the transaction as instructed. Authentication does not guarantee stock, a promotional price, or a fulfillment schedule.

## Evaluation Instrument and Historical Results

For a future evaluation of this revision, replace the item "The system supports both guest checkout and registered customer ordering workflows" with "The system supports guest catalog browsing and requires customer authentication before cart, request, and order actions, while preserving valid product selections during authentication."

Do not retrospectively change an administered questionnaire, its collected responses, or its reported scores. Identify the account-required ordering flow as a system revision and record its regression testing separately. Any new evaluation results must come from an actual evaluation of this version.
