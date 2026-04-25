<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalDataSeeder extends Seeder
{
    public function run(): void
    {
        // Users
        DB::table('users')->upsert([
            ['id'=>'0qm0ed7vjr11','fullname'=>'Jose Alfredo Barrozo','email'=>'josealfredobarrozo75@gmail.com','phone'=>'jose1','username'=>'jose2','password'=>'$2y$10$DABJOkESLINmHTWvL7EvZOjTBwVaxPCdN6IP6LG6O1.Pk3ZmVSf.i','role'=>'superadmin','is_verified'=>1,'created_at'=>'2026-04-18 07:11:24','updated_at'=>'2026-04-19 01:51:49'],
            ['id'=>'3rn560r8udac','fullname'=>'Bec Castillo','email'=>'barrozos004@gmail.com','phone'=>'+639104587030','username'=>'bec-castillo50','password'=>'$2y$10$3Wx/l49HJqv0jeTinowruO/.qoEyyziUmaaNku.BukRzR8miFx/Qq','role'=>'seller','is_verified'=>1,'created_at'=>'2026-04-18 13:52:33','updated_at'=>'2026-04-19 01:51:49'],
        ], ['id'], ['fullname','email','phone','username','password','role','is_verified','updated_at']);

        // Shops
        DB::table('shops')->upsert([
            ['id'=>'zwn37a5y0g0h','seller_id'=>'3rn560r8udac','shop_name'=>'Simple Cake shop','shop_slug'=>'simple-cake-shop','shop_logo'=>'/storage/uploads/shops/20260419095610_2be6d501.png','shop_cover'=>'/storage/uploads/shops/20260419095610_ebcf495b.png','description'=>null,'address'=>'Bautista Pangasinan','city'=>'Bautista','contact_number'=>'+639104587030','gcash_number'=>'+639104587030','theme_color'=>'#eb2d5d','status'=>'approved','tier'=>'basic','commission_rate'=>3.00,'rejected_reason'=>null,'verified_at'=>'2026-04-18 13:55:06','created_at'=>'2026-04-18 13:52:34','updated_at'=>'2026-04-20 08:47:55'],
        ], ['id'], ['shop_name','shop_slug','status','updated_at']);

        // Seller documents
        DB::table('seller_documents')->upsert([
            ['id'=>1,'shop_id'=>'zwn37a5y0g0h','document_type'=>'valid_id','file_path'=>'/storage/uploads/seller_docs/20260418215234_ca622c0f.png','created_at'=>'2026-04-18 13:52:34','updated_at'=>'2026-04-18 13:52:34'],
        ], ['id'], ['file_path']);

        // Platform settings (no API keys)
        DB::table('platform_settings')->upsert([
            ['id'=>1,'platform_name'=>'Berry Base','platform_logo'=>'/storage/uploads/platform/20260421020520_49ab427a.png','platform_tagline'=>'Your Local Cake Shop','platform_email'=>'josealfredobarrozo75@gmail.com','platform_phone'=>'09104587030','commission_rate_basic'=>3.00,'commission_rate_verified'=>2.00,'max_products_basic'=>20,'paymongo_mode'=>'test','created_at'=>'2026-04-18 07:03:35','updated_at'=>'2026-04-24 12:09:44'],
        ], ['id'], ['platform_name','platform_logo','platform_tagline','updated_at']);

        // Site settings (no API keys)
        DB::table('site_settings')->upsert([
            ['id'=>1,'shop_id'=>null,'site_title'=>'Cake Shop Platform','tagline'=>'Your Local Cakeshops','logo_path'=>'','bg_type'=>'gradient','bg_color'=>'#FFF8F8','gradient_start'=>'#FFF8F8','gradient_end'=>'#FFE0E0','bg_image_opacity'=>0.18,'primary_color'=>'#E53935','paymongo_mode'=>'test','vat_rate'=>0.00,'vat_enabled'=>0,'daily_max_cakes'=>0,'lead_1day_max'=>0,'lead_2day_max'=>0,'lead_3day_plus_max'=>0,'timezone'=>'Asia/Manila','updated_at'=>'2026-04-21 04:29:58'],
            ['id'=>2,'shop_id'=>'zwn37a5y0g0h','site_title'=>'My Cake Shop','tagline'=>'Your Local Cakeshops','logo_path'=>'','bg_type'=>'gradient','bg_color'=>'#ffffff','gradient_start'=>'#fff7fb','gradient_end'=>'#ffe3f1','bg_image_opacity'=>0.18,'primary_color'=>'#E53935','paymongo_mode'=>'test','vat_rate'=>0.00,'vat_enabled'=>0,'daily_max_cakes'=>2,'lead_1day_max'=>3,'lead_2day_max'=>5,'lead_3day_plus_max'=>7,'timezone'=>'Asia/Manila','updated_at'=>'2026-04-21 04:29:58'],
        ], ['id'], ['site_title','updated_at']);

        // Products
        DB::table('products')->upsert([
            ['id'=>'4m1yg6CA9w','shop_id'=>'zwn37a5y0g0h','name'=>'Mocha Truffle Cake','description'=>'Smooth mocha cream cake with Maltesers, chocolate wafer sticks, and Hershey kisses. Chocolate drip border adds indulgence to this irresistible creation.','price'=>1050.00,'image_path'=>'/storage/uploads/products/20260414105851_655e51a9ce8f.jpg','classification'=>'Standard','flavor'=>'Mocha','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'71KQuX0ZZ5','shop_id'=>'zwn37a5y0g0h','name'=>'Mango Cream Cake','description'=>'Yellow buttercream cake with sweet mango jam center, piped rosette border, and chocolate drip. A Filipino tropical favorite.','price'=>950.00,'image_path'=>'/storage/uploads/products/20260414105850_5d2503854f31.jpg','classification'=>'Standard','flavor'=>'Mango','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'8yqDYKMrgQ','shop_id'=>'zwn37a5y0g0h','name'=>'Oreo Cream Mini Cake','description'=>'Compact cookies and cream cake with Oreo crumble base, cream cheese-style buttercream, white chocolate shard toppers, and dark Oreo crumble center.','price'=>850.00,'image_path'=>'/storage/uploads/products/20260414105851_714c862fcc4e.jpg','classification'=>'Standard','flavor'=>'Cookies & Cream','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'90aiTkkl0N','shop_id'=>'zwn37a5y0g0h','name'=>'Dark Chocolate Matcha Cake','description'=>'Dark chocolate glazed cake with striking matcha green drizzle. Available as layered naked cake or fully glazed. Bold flavors for the adventurous cake lover.','price'=>1100.00,'image_path'=>'/storage/uploads/products/20260414105850_52e136ac2d1b.jpg','classification'=>'Standard','flavor'=>'Dark Chocolate','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'C8cl0r1lyJ','shop_id'=>'zwn37a5y0g0h','name'=>'Pure White Drip Cake','description'=>'Pristine white buttercream cake with soft rosette border, gold pearl accents, and chocolate drip. Simple and elegant for any celebration.','price'=>900.00,'image_path'=>'/storage/uploads/products/20260414105851_d7697c6396bf.jpg','classification'=>'Standard','flavor'=>'Vanilla','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'cEyDq3rg8V','shop_id'=>'zwn37a5y0g0h','name'=>'Double Tier Chocolate Cake','description'=>'Impressive two-tier all-chocolate buttercream cake with textured piping on the bottom tier and elegant swirl piping on top. A grand centerpiece for milestone celebrations.','price'=>2800.00,'image_path'=>'/storage/uploads/products/20260414105851_1af75b5458ff.jpg','classification'=>'Standard','flavor'=>'Chocolate','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'CthMkIqd78','shop_id'=>'zwn37a5y0g0h','name'=>'Mocha Rosette Drip Cake','description'=>'Mocha buttercream cake with full rosette piping on top, festive sprinkles, and generous chocolate drip. Bold coffee-chocolate flavor.','price'=>1000.00,'image_path'=>'/storage/uploads/products/20260414105851_0373ae24bcfd.jpg','classification'=>'Standard','flavor'=>'Mocha','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'CzLwa3ZEsM','shop_id'=>'zwn37a5y0g0h','name'=>'Cookies and Cream Cake','description'=>'White cake coated in Oreo crumble base, topped with cookies and cream buttercream rosettes and white chocolate chunks. A cookies and cream lover\'s dream.','price'=>1050.00,'image_path'=>'/storage/uploads/products/20260414105850_7f5bb7f3559c.jpg','classification'=>'Standard','flavor'=>'Cookies & Cream','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'dYPpHnL6WW','shop_id'=>'zwn37a5y0g0h','name'=>'Mango Caramel Cake','description'=>'Smooth yellow buttercream cake topped with glossy mango-caramel sauce, shell-piped border, and elegant chocolate drip. Rich tropical sweetness.','price'=>950.00,'image_path'=>'/storage/uploads/products/20260414105850_aa45efb1a62a.jpg','classification'=>'Standard','flavor'=>'Mango','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'EDiAAD5c37','shop_id'=>'zwn37a5y0g0h','name'=>'Classic White Cream Cake','description'=>'Elegant all-white buttercream cake with rosette piping border, gold pearl accents, and chocolate drip. Clean and sophisticated for any occasion.','price'=>900.00,'image_path'=>'/storage/uploads/products/20260414105850_7c64dcbf26e7.jpg','classification'=>'Standard','flavor'=>'Vanilla','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'GUOrWJXU5j','shop_id'=>'zwn37a5y0g0h','name'=>'Hello Kitty Birthday Cake','description'=>'Whimsical Hello Kitty themed birthday cake with pink ruffled buttercream, Hello Kitty figurines, and personalized topper. A dream cake for every Hello Kitty fan.','price'=>1800.00,'image_path'=>'/storage/uploads/products/20260414105851_df781209a412.jpg','classification'=>'Fondant','flavor'=>'Strawberry','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'GVE7mYzBN6','shop_id'=>'zwn37a5y0g0h','name'=>'Blue Berry Swirl Cake','description'=>'Light blue buttercream cake with swirled rosette piping and dark blueberry compote topping. Colorful rainbow sprinkles add a festive touch.','price'=>900.00,'image_path'=>'/storage/uploads/products/20260414105850_cb22e90d4cac.jpg','classification'=>'Standard','flavor'=>'Blueberry','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'JwQgBNllKf','shop_id'=>'zwn37a5y0g0h','name'=>'Cheesy Heaven Cake','description'=>'Rich cake generously topped with freshly grated cheese - sweet, salty, and absolutely irresistible. A Filipino classic that everyone loves.','price'=>800.00,'image_path'=>'/storage/uploads/products/20260414105849_5afbd9b88327.jpg','classification'=>'Standard','flavor'=>'Cheese','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'Kc9q0H4ofL','shop_id'=>'zwn37a5y0g0h','name'=>'Mini Duo Drip Cake','description'=>'Adorable mini cakes with smooth buttercream finish, chocolate drip, and piped rosette crown. Available in Lemon and Vanilla. Perfect for small celebrations.','price'=>650.00,'image_path'=>'/storage/uploads/products/20260414105849_c7cfa486f040.jpg','classification'=>'Standard','flavor'=>'Lemon / Vanilla','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'LNqNWuj4cE','shop_id'=>'zwn37a5y0g0h','name'=>'Mango Delight Cake','description'=>'Bright yellow buttercream cake with piped rosette border, sweet mango jam center, and chocolate drip. Captures the irresistible flavor of ripe Philippine mangoes.','price'=>950.00,'image_path'=>'/storage/uploads/products/20260414105851_2b58a6ae3ab7.jpg','classification'=>'Standard','flavor'=>'Mango','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'orHymHTk1l','shop_id'=>'zwn37a5y0g0h','name'=>'Mocha Chocolate Drip Cake','description'=>'Rich mocha buttercream cake with chocolate drip sides and festive rainbow sprinkles. Deeply flavored with coffee and chocolate.','price'=>950.00,'image_path'=>'/storage/uploads/products/20260414105850_fe5abffab5b9.jpg','classification'=>'Standard','flavor'=>'Mocha','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'oUrd8qDicb','shop_id'=>'zwn37a5y0g0h','name'=>'Blueberry Dream Cake','description'=>'Teal-blue buttercream cake with piped shell border and rich blueberry compote center. Bursting with real blueberry flavor.','price'=>900.00,'image_path'=>'/storage/uploads/products/20260414105850_6ab2b660fa73.jpg','classification'=>'Standard','flavor'=>'Blueberry','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'TlW5zEZ8nm','shop_id'=>'zwn37a5y0g0h','name'=>'Blush White Drip Cake','description'=>'Romantic blush-white buttercream cake with rosette border, gold pearl sprinkles, and chocolate drip. Ideal for weddings, anniversaries, and special occasions.','price'=>950.00,'image_path'=>'/storage/uploads/products/20260414105851_d78affe7275f.jpg','classification'=>'Standard','flavor'=>'Vanilla','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'ugEJM1Psm2','shop_id'=>'zwn37a5y0g0h','name'=>'Blue Velvet Rosette Cake','description'=>'Sky-blue buttercream cake with rosette piping and gold pearl accents. Chocolate drip sides add a luxurious touch. Great for baby showers and birthdays.','price'=>950.00,'image_path'=>'/storage/uploads/products/20260414105849_241d8c0c788e.jpg','classification'=>'Standard','flavor'=>'Vanilla','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'vP10pJUHQk','shop_id'=>'zwn37a5y0g0h','name'=>'Caramel Oreo Drip Cake','description'=>'Yellow buttercream cake with caramel glaze, whole Oreo cookies, and crunchy walnut bits. Rich chocolate drip makes this a showstopper.','price'=>1100.00,'image_path'=>'/storage/uploads/products/20260414105849_21aa4a9976dd.jpg','classification'=>'Standard','flavor'=>'Caramel','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'x0qCd5r66a','shop_id'=>'zwn37a5y0g0h','name'=>'Malteser Cream Cake','description'=>'White buttercream cake with Maltesers chocolate balls, swirled rosette piping, and elegant chocolate drip lines. Sweet and crunchy.','price'=>1000.00,'image_path'=>'/storage/uploads/products/20260414105850_19cb9f0cdca7.jpg','classification'=>'Standard','flavor'=>'Chocolate','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'XGpmi7p4qi','shop_id'=>'zwn37a5y0g0h','name'=>'Butterfly Cupcake Set','description'=>'Chocolate cupcakes with lavender-purple buttercream swirls and butterfly fondant toppers. Perfect for birthdays and garden parties. Sold per dozen.','price'=>480.00,'image_path'=>'/storage/uploads/products/20260414105850_e957b92c6171.jpg','classification'=>'Standard','flavor'=>'Chocolate','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'YagyXUFUbS','shop_id'=>'zwn37a5y0g0h','name'=>'Naruto Theme Cake','description'=>'Naruto Uzumaki themed birthday cake on a golden yellow fondant base with flame toppers and number topper. Perfect for anime fans.','price'=>1800.00,'image_path'=>'/storage/uploads/products/20260414105850_63e628d0dc7b.jpg','classification'=>'Fondant','flavor'=>'Vanilla','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:29:49'],
            ['id'=>'Z18tsQ3CYV','shop_id'=>'zwn37a5y0g0h','name'=>'Mr & Mrs Wedding Cake','description'=>'Stunning two-tier wedding cake with teal fondant base, gold leaf accents, white roses, and a Mr & Mrs acrylic topper. Perfect for weddings and anniversaries.','price'=>3500.00,'image_path'=>'/storage/uploads/products/20260414105849_3b2a26e47e61.jpg','classification'=>'Fondant','flavor'=>'Vanilla','is_available'=>1,'sort_order'=>0,'created_at'=>'2026-04-20 06:34:13','updated_at'=>'2026-04-20 08:31:11'],
        ], ['id'], ['name','price','updated_at']);

        // Riders
        DB::table('riders')->upsert([
            ['id'=>1,'shop_id'=>'zwn37a5y0g0h','name'=>'Jhustyn jhay datuin','nickname'=>'JAB','phone'=>'+639104587030','license_plate'=>'AR4425','vehicle_type'=>'Motorcycle','is_active'=>1,'created_at'=>'2026-04-24 17:45:08','updated_at'=>'2026-04-24 17:45:08'],
        ], ['id'], ['name','updated_at']);

        // Orders — all rows must have identical keys for PostgreSQL upsert
        $orders = [
            ['id'=>'1zb6qvowk0kc','shop_id'=>'zwn37a5y0g0h','product_id'=>'GVE7mYzBN6','guest_name'=>'Jose Alfredo Barrozo','guest_phone'=>'+639104587030','quantity'=>1,'total_price'=>900.00,'delivery_fee'=>0.00,'service_charge'=>0.00,'status'=>'Preparing','fulfillment_type'=>'Delivery','schedule_date'=>'2026-04-25','schedule_time'=>'11:00:00','delivery_address'=>'Nandacan, Bautista, Pangasinan','delivery_zone'=>'Nandacan, Bautista','payment_method'=>'GCash','payment_status'=>'Partial Payment','track_code'=>'7Q782XR3','kitchen_sent'=>1,'deposit_required'=>1,'deposit_amount'=>450.00,'deposit_status'=>'paid','deposit_paymongo_id'=>'cs_dccf868383371ea89dc8e79a','paid_at'=>null,'delivered_at'=>null,'created_at'=>'2026-04-23 19:16:00','updated_at'=>'2026-04-23 23:32:54'],
            ['id'=>'ehztq6pgjxb8','shop_id'=>'zwn37a5y0g0h','product_id'=>'YagyXUFUbS','guest_name'=>'Antonia Sarmiento Barrozo','guest_phone'=>'+639233776385','quantity'=>1,'total_price'=>1800.00,'delivery_fee'=>0.00,'service_charge'=>0.00,'status'=>'Confirmed','fulfillment_type'=>'Delivery','schedule_date'=>'2026-04-25','schedule_time'=>'13:00:00','delivery_address'=>'Municipal Drive, Bongato West, Bautista, Pangasinan','delivery_zone'=>'Poblacion East, Bautista','payment_method'=>'GCash','payment_status'=>'Paid','track_code'=>'XU73LMQN','kitchen_sent'=>1,'deposit_required'=>1,'deposit_amount'=>1800.00,'deposit_status'=>'paid','deposit_paymongo_id'=>'cs_d6ca4088a9b7e4abe8874a4b','paid_at'=>'2026-04-24 12:36:59','delivered_at'=>null,'created_at'=>'2026-04-24 12:35:22','updated_at'=>'2026-04-24 12:36:59'],
            ['id'=>'iiqwnz98fq7c','shop_id'=>'zwn37a5y0g0h','product_id'=>'GVE7mYzBN6','guest_name'=>'Jose Alfredo Barrozo','guest_phone'=>'+639104587030','quantity'=>1,'total_price'=>900.00,'delivery_fee'=>0.00,'service_charge'=>0.00,'status'=>'Pending','fulfillment_type'=>'Delivery','schedule_date'=>'2026-04-22','schedule_time'=>'11:00:00','delivery_address'=>'Bongato West, Bautista, Pangasinan','delivery_zone'=>'Poblacion East, Bautista','payment_method'=>'GCash','payment_status'=>'Unpaid','track_code'=>'AEPHP3EK','kitchen_sent'=>0,'deposit_required'=>1,'deposit_amount'=>450.00,'deposit_status'=>'pending','deposit_paymongo_id'=>null,'paid_at'=>null,'delivered_at'=>null,'created_at'=>'2026-04-21 13:20:44','updated_at'=>'2026-04-21 13:32:43'],
            ['id'=>'k52ay20bgdbi','shop_id'=>'zwn37a5y0g0h','product_id'=>'YagyXUFUbS','guest_name'=>'Prince Alfred Barrozo','guest_phone'=>'+639104587030','quantity'=>1,'total_price'=>1800.00,'delivery_fee'=>0.00,'service_charge'=>0.00,'status'=>'Pending','fulfillment_type'=>'Delivery','schedule_date'=>'2026-04-21','schedule_time'=>'09:00:00','delivery_address'=>'Bayambang-Alcala Road, Anulid, Bautista, Pangasinan','delivery_zone'=>'Poblacion East, Bautista','payment_method'=>'GCash','payment_status'=>'Unpaid','track_code'=>'4YZBAWE5','kitchen_sent'=>0,'deposit_required'=>1,'deposit_amount'=>900.00,'deposit_status'=>'pending','deposit_paymongo_id'=>null,'paid_at'=>null,'delivered_at'=>null,'created_at'=>'2026-04-21 05:26:08','updated_at'=>'2026-04-22 03:55:52'],
            ['id'=>'pcvhvycghwtr','shop_id'=>'zwn37a5y0g0h','product_id'=>'YagyXUFUbS','guest_name'=>'Jose Alfredo Barrozo','guest_phone'=>'+639233776385','quantity'=>1,'total_price'=>1800.00,'delivery_fee'=>0.00,'service_charge'=>0.00,'status'=>'Confirmed','fulfillment_type'=>'Delivery','schedule_date'=>'2026-04-25','schedule_time'=>'13:00:00','delivery_address'=>'M. H. del Pilar Road, Nandacan, Bautista, Pangasinan','delivery_zone'=>'Nandacan, Bautista','payment_method'=>'GCash','payment_status'=>'Partial Payment','track_code'=>'2B85RCHP','kitchen_sent'=>1,'deposit_required'=>1,'deposit_amount'=>900.00,'deposit_status'=>'paid','deposit_paymongo_id'=>'cs_893c17f1f94442aef3b6e3e9','paid_at'=>null,'delivered_at'=>null,'created_at'=>'2026-04-24 12:16:32','updated_at'=>'2026-04-24 12:17:58'],
            ['id'=>'v6j9amwgsywf','shop_id'=>'zwn37a5y0g0h','product_id'=>'YagyXUFUbS','guest_name'=>'ALFREDO BARROZO','guest_phone'=>'+639104587030','quantity'=>1,'total_price'=>1800.00,'delivery_fee'=>0.00,'service_charge'=>0.00,'status'=>'Picked Up','fulfillment_type'=>'Pickup','schedule_date'=>'2026-04-26','schedule_time'=>'09:00:00','delivery_address'=>'','delivery_zone'=>'','payment_method'=>'GCash','payment_status'=>'Paid','track_code'=>'PA65TS8T','kitchen_sent'=>1,'deposit_required'=>1,'deposit_amount'=>900.00,'deposit_status'=>'paid','deposit_paymongo_id'=>'cs_d962dab936ba03b0689d673d','paid_at'=>'2026-04-24 13:25:14','delivered_at'=>'2026-04-24 17:36:37','created_at'=>'2026-04-24 13:15:41','updated_at'=>'2026-04-24 17:36:37'],
        ];
        DB::table('orders')->upsert($orders, ['id'], ['status','payment_status','updated_at']);

        // Order tracking
        DB::table('order_tracking')->upsert([
            ['id'=>1,'order_id'=>'k52ay20bgdbi','status'=>'Pending','notes'=>'Guest order placed.','created_at'=>'2026-04-21 05:26:08'],
            ['id'=>2,'order_id'=>'k52ay20bgdbi','status'=>'Pending','notes'=>'Customer set deposit of 900 via GCash (min 50%).','created_at'=>'2026-04-21 06:55:20'],
            ['id'=>3,'order_id'=>'k52ay20bgdbi','status'=>'Pending','notes'=>'Customer set deposit of 900 via GCash (min 50%).','created_at'=>'2026-04-21 12:06:23'],
            ['id'=>4,'order_id'=>'k52ay20bgdbi','status'=>'Pending','notes'=>'Customer set deposit of 900 via GCash (min 50%).','created_at'=>'2026-04-21 12:08:42'],
            ['id'=>5,'order_id'=>'k52ay20bgdbi','status'=>'Pending','notes'=>'Customer set deposit of 900 via GCash (min 50%).','created_at'=>'2026-04-21 12:12:53'],
            ['id'=>6,'order_id'=>'iiqwnz98fq7c','status'=>'Pending','notes'=>'Guest order placed.','created_at'=>'2026-04-21 13:20:44'],
            ['id'=>7,'order_id'=>'iiqwnz98fq7c','status'=>'Pending','notes'=>'Customer set deposit of 450 via GCash (min 50%).','created_at'=>'2026-04-21 13:32:41'],
            ['id'=>8,'order_id'=>'k52ay20bgdbi','status'=>'Pending','notes'=>'Customer set deposit of 900 via GCash (min 50%).','created_at'=>'2026-04-22 03:55:51'],
            ['id'=>9,'order_id'=>'1zb6qvowk0kc','status'=>'Pending','notes'=>'Guest order placed.','created_at'=>'2026-04-23 19:16:00'],
            ['id'=>10,'order_id'=>'1zb6qvowk0kc','status'=>'Pending','notes'=>'Customer set deposit of 450 via GCash (min 50%).','created_at'=>'2026-04-23 19:16:31'],
            ['id'=>11,'order_id'=>'1zb6qvowk0kc','status'=>'Pending','notes'=>'Customer set deposit of 450 via GCash (min 50%).','created_at'=>'2026-04-23 19:30:35'],
            ['id'=>12,'order_id'=>'1zb6qvowk0kc','status'=>'Deposit Paid','notes'=>'Deposit of 450.00 paid via GCash.','created_at'=>'2026-04-23 19:32:15'],
            ['id'=>13,'order_id'=>'1zb6qvowk0kc','status'=>'Confirmed','notes'=>'Auto-confirmed after deposit payment via GCash.','created_at'=>'2026-04-23 19:32:15'],
            ['id'=>14,'order_id'=>'1zb6qvowk0kc','status'=>'Preparing','notes'=>'Kitchen started preparing the order.','created_at'=>'2026-04-23 23:32:54'],
            ['id'=>15,'order_id'=>'pcvhvycghwtr','status'=>'Pending','notes'=>'Guest order placed.','created_at'=>'2026-04-24 12:16:32'],
            ['id'=>16,'order_id'=>'pcvhvycghwtr','status'=>'Pending','notes'=>'Customer set deposit of 900 via GCash (min 50%).','created_at'=>'2026-04-24 12:17:08'],
            ['id'=>17,'order_id'=>'pcvhvycghwtr','status'=>'Deposit Paid','notes'=>'Deposit of 900.00 paid via GCash.','created_at'=>'2026-04-24 12:17:58'],
            ['id'=>18,'order_id'=>'pcvhvycghwtr','status'=>'Confirmed','notes'=>'Auto-confirmed after deposit payment via GCash.','created_at'=>'2026-04-24 12:17:58'],
            ['id'=>19,'order_id'=>'ehztq6pgjxb8','status'=>'Pending','notes'=>'Guest order placed.','created_at'=>'2026-04-24 12:35:22'],
            ['id'=>20,'order_id'=>'ehztq6pgjxb8','status'=>'Pending','notes'=>'Customer chose to pay full amount 1800 via GCash.','created_at'=>'2026-04-24 12:36:29'],
            ['id'=>21,'order_id'=>'ehztq6pgjxb8','status'=>'Deposit Paid','notes'=>'Deposit of 1800.00 paid via GCash.','created_at'=>'2026-04-24 12:36:59'],
            ['id'=>22,'order_id'=>'ehztq6pgjxb8','status'=>'Confirmed','notes'=>'Auto-confirmed after deposit payment via GCash.','created_at'=>'2026-04-24 12:36:59'],
            ['id'=>23,'order_id'=>'v6j9amwgsywf','status'=>'Pending','notes'=>'Guest order placed.','created_at'=>'2026-04-24 13:15:41'],
            ['id'=>24,'order_id'=>'v6j9amwgsywf','status'=>'Pending','notes'=>'Customer set deposit of 900 via GCash (min 50%).','created_at'=>'2026-04-24 13:20:54'],
            ['id'=>25,'order_id'=>'v6j9amwgsywf','status'=>'Deposit Paid','notes'=>'Deposit of 900.00 paid via GCash.','created_at'=>'2026-04-24 13:21:17'],
            ['id'=>26,'order_id'=>'v6j9amwgsywf','status'=>'Confirmed','notes'=>'Auto-confirmed after deposit payment via GCash.','created_at'=>'2026-04-24 13:21:17'],
            ['id'=>27,'order_id'=>'v6j9amwgsywf','status'=>'Preparing','notes'=>'Kitchen started preparing the order.','created_at'=>'2026-04-24 13:23:16'],
            ['id'=>28,'order_id'=>'v6j9amwgsywf','status'=>'Picked Up','notes'=>'','created_at'=>'2026-04-24 17:36:37'],
        ], ['id'], ['status','notes']);

        // Kitchen tickets
        DB::table('kitchen_tickets')->upsert([
            ['id'=>1,'shop_id'=>'zwn37a5y0g0h','order_id'=>'1zb6qvowk0kc','product_name'=>'Blue Berry Swirl Cake','product_image'=>'/storage/uploads/products/20260414105850_cb22e90d4cac.jpg','quantity'=>1,'status'=>'in_progress','sent_at'=>'2026-04-23 19:59:37','created_at'=>'2026-04-23 19:59:37','updated_at'=>'2026-04-23 23:32:54'],
            ['id'=>2,'shop_id'=>'zwn37a5y0g0h','order_id'=>'pcvhvycghwtr','product_name'=>'Naruto Theme Cake','product_image'=>'/storage/uploads/products/20260414105850_63e628d0dc7b.jpg','quantity'=>1,'status'=>'pending','sent_at'=>'2026-04-24 12:17:58','created_at'=>'2026-04-24 12:17:58','updated_at'=>'2026-04-24 12:17:58'],
            ['id'=>3,'shop_id'=>'zwn37a5y0g0h','order_id'=>'ehztq6pgjxb8','product_name'=>'Naruto Theme Cake','product_image'=>'/storage/uploads/products/20260414105850_63e628d0dc7b.jpg','quantity'=>1,'status'=>'pending','sent_at'=>'2026-04-24 12:36:59','created_at'=>'2026-04-24 12:36:59','updated_at'=>'2026-04-24 12:36:59'],
            ['id'=>4,'shop_id'=>'zwn37a5y0g0h','order_id'=>'v6j9amwgsywf','product_name'=>'Naruto Theme Cake','product_image'=>'/storage/uploads/products/20260414105850_63e628d0dc7b.jpg','quantity'=>1,'status'=>'done','sent_at'=>'2026-04-24 13:21:17','created_at'=>'2026-04-24 13:21:17','updated_at'=>'2026-04-24 13:23:34'],
        ], ['id'], ['status','updated_at']);

        // Notifications — all rows must have identical keys
        DB::table('notifications')->upsert([
            ['id'=>1,'receiver_role'=>'superadmin','receiver_user_id'=>'0qm0ed7vjr11','title'=>'New Seller Application','message'=>'Simple Cake shop applied to become a Basic Seller.','is_read'=>0,'created_at'=>'2026-04-18 13:52:34'],
            ['id'=>2,'receiver_role'=>'admin','receiver_user_id'=>null,'title'=>'New Order from Prince Alfred Barrozo','message'=>'Prince Alfred Barrozo (+639104587030) placed Order #k52ay20bgdbi.','is_read'=>0,'created_at'=>'2026-04-21 05:26:08'],
            ['id'=>3,'receiver_role'=>'admin','receiver_user_id'=>null,'title'=>'New Order from Jose Alfredo Barrozo','message'=>'Jose Alfredo Barrozo (+639104587030) placed Order #iiqwnz98fq7c.','is_read'=>0,'created_at'=>'2026-04-21 13:20:44'],
            ['id'=>4,'receiver_role'=>'admin','receiver_user_id'=>null,'title'=>'New Order from Jose Alfredo Barrozo','message'=>'Jose Alfredo Barrozo (+639104587030) placed Order #1zb6qvowk0kc.','is_read'=>0,'created_at'=>'2026-04-23 19:16:00'],
            ['id'=>5,'receiver_role'=>'admin','receiver_user_id'=>null,'title'=>'New Order from Jose Alfredo Barrozo','message'=>'Jose Alfredo Barrozo (+639233776385) placed Order #pcvhvycghwtr.','is_read'=>0,'created_at'=>'2026-04-24 12:16:32'],
            ['id'=>6,'receiver_role'=>'admin','receiver_user_id'=>null,'title'=>'New Order from Antonia Sarmiento Barrozo','message'=>'Antonia Sarmiento Barrozo (+639233776385) placed Order #ehztq6pgjxb8.','is_read'=>0,'created_at'=>'2026-04-24 12:35:22'],
            ['id'=>7,'receiver_role'=>'admin','receiver_user_id'=>null,'title'=>'Deposit Paid - Order #ehztq6pgjxb8','message'=>'Antonia Sarmiento Barrozo paid the deposit of PHP 1800.00 for Order #ehztq6pgjxb8.','is_read'=>0,'created_at'=>'2026-04-24 12:36:59'],
            ['id'=>8,'receiver_role'=>'admin','receiver_user_id'=>null,'title'=>'New Order from ALFREDO BARROZO','message'=>'ALFREDO BARROZO (+639104587030) placed Order #v6j9amwgsywf.','is_read'=>0,'created_at'=>'2026-04-24 13:15:41'],
            ['id'=>9,'receiver_role'=>'admin','receiver_user_id'=>null,'title'=>'Deposit Paid - Order #v6j9amwgsywf','message'=>'ALFREDO BARROZO paid the deposit of PHP 900.00 for Order #v6j9amwgsywf.','is_read'=>0,'created_at'=>'2026-04-24 13:21:17'],
            ['id'=>10,'receiver_role'=>'admin','receiver_user_id'=>null,'title'=>'GCash Payment Received - Order #v6j9amwgsywf','message'=>'ALFREDO BARROZO completed GCash payment for Order #v6j9amwgsywf.','is_read'=>0,'created_at'=>'2026-04-24 13:25:14'],
        ], ['id'], ['title','is_read']);

        // Delivery zones (first 10 only as sample - full list is 132 zones)
        $this->seedDeliveryZones();
    }

    private function seedDeliveryZones(): void
    {
        $zones = [
            [1,'zwn37a5y0g0h','Baluyot, Bautista',0.00,1,1,'near'],
            [2,'zwn37a5y0g0h','Buenlag, Bautista',0.00,2,1,'near'],
            [3,'zwn37a5y0g0h','Burgos, Bautista',0.00,3,1,'near'],
            [4,'zwn37a5y0g0h','Cacandungan, Bautista',0.00,4,1,'near'],
            [5,'zwn37a5y0g0h','Caramutan, Bautista',0.00,5,1,'near'],
            [6,'zwn37a5y0g0h','La Paz, Bautista',0.00,6,1,'near'],
            [7,'zwn37a5y0g0h','Maliwalo, Bautista',0.00,7,1,'near'],
            [8,'zwn37a5y0g0h','Mapalad, Bautista',0.00,8,1,'near'],
            [9,'zwn37a5y0g0h','Nalneran, Bautista',0.00,9,1,'near'],
            [10,'zwn37a5y0g0h','Nancamaliran East, Bautista',0.00,10,1,'near'],
            [11,'zwn37a5y0g0h','Nancamaliran West, Bautista',0.00,11,1,'near'],
            [12,'zwn37a5y0g0h','Nandacan, Bautista',0.00,12,1,'near'],
            [13,'zwn37a5y0g0h','Noblong, Bautista',0.00,13,1,'near'],
            [14,'zwn37a5y0g0h','Palisoc, Bautista',0.00,14,1,'near'],
            [15,'zwn37a5y0g0h','Poblacion East, Bautista',0.00,15,1,'near'],
            [16,'zwn37a5y0g0h','Poblacion West, Bautista',0.00,16,1,'near'],
            [17,'zwn37a5y0g0h','Pugaro, Bautista',0.00,17,1,'near'],
            [18,'zwn37a5y0g0h','Rajal Norte, Bautista',0.00,18,1,'near'],
            [19,'zwn37a5y0g0h','Rajal Sur, Bautista',0.00,19,1,'near'],
            [20,'zwn37a5y0g0h','San Pedro, Bautista',0.00,20,1,'near'],
            [21,'zwn37a5y0g0h','San Vicente, Bautista',0.00,21,1,'near'],
            [22,'zwn37a5y0g0h','Songkoy, Bautista',0.00,22,1,'near'],
            [23,'zwn37a5y0g0h','Talogtog, Bautista',0.00,23,1,'near'],
            [24,'zwn37a5y0g0h','Tara, Bautista',0.00,24,1,'near'],
            [25,'zwn37a5y0g0h','Tomling, Bautista',0.00,25,1,'near'],
            [26,'zwn37a5y0g0h','Abot, Bayambang',30.00,26,1,'near'],
            [27,'zwn37a5y0g0h','Anolid, Bayambang',30.00,27,1,'near'],
            [28,'zwn37a5y0g0h','Bansing, Bayambang',30.00,28,1,'near'],
            [29,'zwn37a5y0g0h','Baro, Bayambang',30.00,29,1,'near'],
            [30,'zwn37a5y0g0h','Bayog, Bayambang',30.00,30,1,'near'],
            [31,'zwn37a5y0g0h','Bobon A, Bayambang',30.00,31,1,'near'],
            [32,'zwn37a5y0g0h','Bobon B, Bayambang',30.00,32,1,'near'],
            [33,'zwn37a5y0g0h','Bobon C, Bayambang',30.00,33,1,'near'],
            [34,'zwn37a5y0g0h','Bobon D, Bayambang',30.00,34,1,'near'],
            [35,'zwn37a5y0g0h','Bugtong Balo, Bayambang',30.00,35,1,'near'],
            [36,'zwn37a5y0g0h','Bugtong Cutol, Bayambang',30.00,36,1,'near'],
            [37,'zwn37a5y0g0h','Bugtong na Munti, Bayambang',30.00,37,1,'near'],
            [38,'zwn37a5y0g0h','Calbueg, Bayambang',30.00,38,1,'near'],
            [39,'zwn37a5y0g0h','Canarem, Bayambang',30.00,39,1,'near'],
            [40,'zwn37a5y0g0h','Carbon, Bayambang',30.00,40,1,'near'],
            [54,'zwn37a5y0g0h','Poblacion (Bayambang)',50.00,54,1,'mid'],
            [55,'zwn37a5y0g0h','Poblacion Sur',50.00,55,1,'mid'],
            [56,'zwn37a5y0g0h','Malimpec East',50.00,56,1,'mid'],
            [57,'zwn37a5y0g0h','Malimpec West',50.00,57,1,'mid'],
            [58,'zwn37a5y0g0h','Malioer',50.00,58,1,'mid'],
            [105,'zwn37a5y0g0h','Alba, Bayambang',80.00,105,1,'far'],
            [106,'zwn37a5y0g0h','Amampeque, Bayambang',80.00,106,1,'far'],
            [107,'zwn37a5y0g0h','Atab, Bayambang',80.00,107,1,'far'],
            [108,'zwn37a5y0g0h','Bacnono, Bayambang',80.00,108,1,'far'],
            [132,'zwn37a5y0g0h','Tricao, Bayambang',80.00,132,1,'far'],
        ];

        $rows = array_map(fn($z) => [
            'id'        => $z[0],
            'shop_id'   => $z[1],
            'barangay'  => $z[2],
            'fee'       => $z[3],
            'sort_order'=> $z[4],
            'is_active' => $z[5],
            'zone_type' => $z[6],
        ], $zones);

        DB::table('delivery_zones')->upsert($rows, ['id'], ['barangay','fee']);
    }
}
