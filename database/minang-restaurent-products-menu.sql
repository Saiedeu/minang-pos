-- ===================================================================
-- LANGIT MINANG RESTAURANT - COMPLETE MENU
-- Total: 13 Categories + 121 Products
-- ===================================================================

-- INSERT CATEGORIES
INSERT INTO `categories` (`name`, `name_ar`, `description`, `icon`, `sort_order`, `is_active`) VALUES
('Sarapan', 'الإفطار', 'Traditional Indonesian breakfast dishes', 'fas fa-sun', 1, 1),
('Ayam', 'الدجاج', 'Chicken dishes with various cooking styles', 'fas fa-drumstick-bite', 2, 1),
('Daging', 'اللحم', 'Beef and meat specialties', 'fas fa-meat', 3, 1),
('Ikan', 'الأسماك والمأكولات البحرية', 'Fish and seafood dishes', 'fas fa-fish', 4, 1),
('A La Carte', 'الأطباق الخاصة', 'Special complete meal dishes', 'fas fa-utensils', 5, 1),
('Sayuran Plus', 'الخضروات', 'Vegetables and side dishes', 'fas fa-leaf', 6, 1),
('Nasi', 'الأرز', 'Rice dishes and fried rice varieties', 'fas fa-bowl-rice', 7, 1),
('Mie', 'المعكرونة', 'Noodles and pasta dishes', 'fas fa-stroopwafel', 8, 1),
('Soto Plus Sup', 'الحساء', 'Traditional soups and broths', 'fas fa-soup', 9, 1),
('Sate', 'الشواء', 'Grilled satay and barbecue', 'fas fa-fire', 10, 1),
('Minuman', 'المشروبات', 'Beverages and drinks', 'fas fa-coffee', 11, 1),
('Desserts Plus', 'الحلويات', 'Desserts and sweet treats', 'fas fa-ice-cream', 12, 1),
('Kids', 'وجبات الأطفال', 'Kids special meals', 'fas fa-child', 13, 1);

-- INSERT ALL PRODUCTS
INSERT INTO `products` (`code`, `name`, `name_ar`, `description`, `ingredients`, `category_id`, `cost_price`, `sell_price`, `quantity`, `unit`, `reorder_level`, `list_in_pos`, `is_active`) VALUES

-- SARAPAN (BREAKFAST) - Category ID 1
('001', 'Lontong Sayur', 'لونتونغ سايور', 'Traditional vegetable soup with rice cake, served with crackers and chili sauce', 'Vegetables, Rice cake, Crackers, Chili sauce', 1, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('002', 'Bubur Ayam', 'بوبور أيام', 'Comforting chicken rice porridge with traditional toppings', 'Rice porridge, Chili sauce, Crackers', 1, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('003', 'Nasi Uduk', 'ناسي أودوك', 'Fragrant coconut rice with mixed toppings and accompaniments', 'Rice, Omelet, Fried Chicken, Peanuts, Fried anchovies, Cucumber', 1, 0.00, 27.00, 20, 'PCS', 5, 1, 1),
('004', 'Nasi Pecel', 'ناسي بيسيل', 'Rice served with fresh vegetables and spicy peanut sauce', 'Rice, Vegetables, Peanut sauce, Crackers, Tempe, Fried Tofu, Fried Egg', 1, 0.00, 26.00, 20, 'PCS', 5, 1, 1),
('005', 'Nasi Kuning', 'ناسي كونينغ', 'Festive yellow rice with traditional side dishes', 'Yellow Rice, Omelet, Tempe, Fried Peanut and Anchovies, Fried Chicken', 1, 0.00, 26.00, 20, 'PCS', 5, 1, 1),

-- AYAM (CHICKEN) - Category ID 2
('006', 'Ayam Goreng', 'دجاج مقلي', 'Crispy fried chicken with aromatic spices and coconut', 'Chicken, Coconut, Galanga, Spices', 2, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('007', 'Ayam Kalio', 'دجاج كاليو', 'Tender chicken in rich coconut curry sauce', 'Chicken, Coconut Milk, Curry Spices', 2, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('008', 'Ayam Bakar', 'دجاج مشوي', 'Grilled chicken marinated in coconut milk and spices', 'Chicken, Coconut Milk, Curry Spices', 2, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('009', 'Ayam Pop', 'دجاج بوب', 'Special steamed chicken with star anise and coconut water', 'Chicken, Star Anise, Coconut Water', 2, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('010', 'Ayam Cabe Hijau', 'دجاج الفلفل الأخضر', 'Spicy chicken with fresh green chilies', 'Chicken, Spices, Green Chili', 2, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('011', 'Ayam Balado Merah', 'دجاج بالادو الأحمر', 'Chicken in spicy red chili sauce', 'Chicken, Spices, Red Chili', 2, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('012', 'Gulai Telur', 'جولاي البيض', 'Eggs in aromatic coconut curry', 'Egg, Coconut Milk, Curry Spices', 2, 0.00, 8.00, 20, 'PCS', 5, 1, 1),
('013', 'Telur Balado', 'بيض بالادو', 'Hard-boiled eggs in spicy red chili sauce', 'Egg, Red Chili Sauce', 2, 0.00, 6.00, 20, 'PCS', 5, 1, 1),
('014', 'Dadar Telur Padang', 'داهدار البيض بادانغ', 'Padang-style omelet with chilies and coconut', 'Egg, Red Chili, Spring Onion, Coconut', 2, 0.00, 10.00, 20, 'PCS', 5, 1, 1),
('015', 'Ati Ayam Balado', 'كبد الدجاج بالادو', 'Chicken liver in spicy red chili sauce', 'Chicken Liver, Red Chili', 2, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('016', 'Telor Barendo', 'بيض بارندو', 'Spicy scrambled eggs with chilies and onions', 'Egg, Red Chili, Spring Onion', 2, 0.00, 10.00, 20, 'PCS', 5, 1, 1),

-- DAGING (BEEF/MEAT) - Category ID 3
('017', 'Beef Rendang', 'رندانغ اللحم', 'Authentic spicy beef slow-cooked in coconut milk and aromatic spices', 'Beef Topside, Coconut Milk, Minang Spices, Chili', 3, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('018', 'Dendeng Batokok', 'دندينغ باتوكوك', 'Pounded beef jerky with traditional spices', 'Beef Topside, Coriander, Shallot', 3, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('019', 'Dendeng Basah', 'دندينغ باساه', 'Wet-style beef jerky with aromatic herbs', 'Beef Topside, Coriander, Shallot', 3, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('020', 'Dendeng Bakar Cabe Hijau', 'دندينغ مشوي بالفلفل الأخضر', 'Grilled beef jerky with green chilies and coconut milk', 'Beef Topside, Green Chili, Coconut Milk', 3, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('021', 'Dendeng Balado Kentang', 'دندينغ بالادو البطاطس', 'Beef jerky with potatoes in spicy red chili sauce', 'Beef Topside, Potato, Red Chili Sauce', 3, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('022', 'Gulai Daging Cincang', 'جولاي اللحم المفروم', 'Minced beef in rich coconut curry', 'Beef, Coconut Milk, Minang Spices', 3, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('023', 'Paru Goreng', 'رئة مقلية', 'Crispy fried beef lungs in light batter', 'Beef Lungs, Batter', 3, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('024', 'Gulai Kikil', 'جولاي كيكيل', 'Beef leg tendon in aromatic coconut curry', 'Beef Leg, Coconut Milk, Minang Curry Spices', 3, 0.00, 15.00, 20, 'PCS', 5, 1, 1),

-- IKAN (FISH/SEAFOOD) - Category ID 4
('025', 'Ikan Bakar (Mackarel/Jesh)', 'سمك مشوي (ماكريل/جيش)', 'Grilled mackerel or jesh fish with traditional Minang spices', 'Mackarel/Jesh, Minang Spices', 4, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('026', 'Balado Tongkol', 'تونة بالادو', 'Tuna fish in spicy red chili sauce', 'Tuna, Red Chili Sauce', 4, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('027', 'Peyek Udang', 'بييك أودانغ', 'Crispy shrimp crackers made with coconut milk and rice flour', 'Shrimp, Coconut Milk, Rice Flour', 4, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('028', 'Gulai Cumi', 'جولاي الحبار', 'Squid in aromatic red curry sauce', 'Squid, Minang Red Curry Spices', 4, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('029', 'Cumi Cabe Hijau', 'حبار بالفلفل الأخضر', 'Stir-fried squid with fresh green chilies', 'Squid, Green Chili', 4, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('030', 'Udang Balado Kentang', 'جمبري بالادو البطاطس', 'Shrimp and potatoes in spicy red chili sauce', 'Shrimp, Potato, Red Chili Sauce', 4, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('031', 'Gulai Kepala Ikan', 'جولاي رأس السمك', 'Premium snapper head curry in rich coconut sauce', 'Snapper Head, Minang Curry Spices', 4, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('032', 'Ikan Asam Padeh (Tongkol/Tenggiri)', 'سمك حامض بادييه', 'Tangy and spicy king fish or tuna in tamarind curry', 'King Fish/Tuna, Chili, Minang Spices', 4, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('033', 'Ikan Tauco Sambal Hijau (Tuna/Sherry)', 'سمك تاوكو سامبال أخضر', 'Tuna or sherry fish with bean sauce and green chili', 'Tuna Sherry, Bean Sauce, Green Chili', 4, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('034', 'Kepiting Saus Padang', 'سلطعون صوص بادانغ', 'Fresh crab in special Padang sauce', 'Crab, Saus Padang', 4, 0.00, 35.00, 20, 'PCS', 5, 1, 1),
('035', 'Udang Saus Padang', 'جمبري صوص بادانغ', 'Fresh prawns in signature Padang sauce', 'Prawns, Saus Padang', 4, 0.00, 35.00, 20, 'PCS', 5, 1, 1),

-- A LA CARTE - Category ID 5
('036', 'Ayam Penyet', 'دجاج بينييت', 'Smashed fried chicken served with rice, salad and spicy chili sauce', 'Rice, Fried Chicken, Salad, Red Chili Sauce', 5, 0.00, 28.00, 20, 'PCS', 5, 1, 1),
('037', 'Ayam Bakar Bumbu Bali', 'دجاج مشوي بتوابل بالي', 'Bali-style grilled chicken with aromatic spices and rice', 'Rice, Fried Chicken, Bali Chili Sauce', 5, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('038', 'Ayam Rica-Rica', 'دجاج ريكا ريكا', 'Spicy chicken in rica-rica sauce with rice', 'Rice, Fried Chicken, Rica-Rica Chili Sauce', 5, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('039', 'Ayam Bakar Bumbu Kecap', 'دجاج مشوي بصوص الكيتشاب', 'Barbecue chicken in sweet soy sauce with rice and salad', 'Rice, Barbecue Chicken, Salad, Sweet Soya Sauce', 5, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('040', 'Ayam', 'دجاج', 'Braised chicken with potato served with rice', 'Rice, Braised Chicken, Potato', 5, 0.00, 28.00, 20, 'PCS', 5, 1, 1),
('041', 'Ikan Bakar Bumbu Kecap (Tilapia)', 'سمك بلطي مشوي بصوص الكيتشاب', 'Grilled tilapia in sweet soy sauce with rice and salad', 'Rice, Barbecue Tilapia, Sweet Soya Sauce, Salad', 5, 0.00, 28.00, 20, 'PCS', 5, 1, 1),
('042', 'Gado Gado', 'جادو جادو', 'Traditional Indonesian salad with peanut sauce and mixed vegetables', 'Peanut Sauce, Vegetables, Rice Cake, Boiled Egg, Crackers', 5, 0.00, 26.00, 20, 'PCS', 5, 1, 1),
('043', 'Pecel Lele', 'بيسيل ليلي', 'Fried catfish with rice, salad and spicy chili sauce', 'Rice, Cat fish, Salad, Red Chili Sauce', 5, 0.00, 26.00, 20, 'PCS', 5, 1, 1),
('044', 'Tempe Sambel Kemangi', 'تيمبي سامبال كيمانجي', 'Tempeh with basil and spicy sauce served with rice', 'Rice, Basil, Tempeh, Salad, Red Chili Sauce', 5, 0.00, 20.00, 20, 'PCS', 5, 1, 1),
('045', 'Tahu/Tempe Goreng Penyet', 'تاهو/تيمبي مقلي بينييت', 'Smashed fried tofu or tempeh with rice and chili sauce', 'Rice, Bean Curd/Tempeh, Red Chili Sauce', 5, 0.00, 20.00, 20, 'PCS', 5, 1, 1),
('046', 'Telur Dadar Penyet', 'بيض داهدار بينييت', 'Smashed omelet with rice, salad and spicy chili sauce', 'Rice, Omelet, Salad, Red Chili Sauce', 5, 0.00, 18.00, 20, 'PCS', 5, 1, 1),
('047', 'Martabak Telor', 'مرطبك البيض', 'Stuffed pancake with egg, ground beef and spices', 'Egg, Ground Beef, Spices', 5, 0.00, 20.00, 20, 'PCS', 5, 1, 1),
('048', 'Rawon', 'راوون', 'Traditional black soup with beef, bean sprouts and rice', 'Rice, Blackened Beef Soup, Bean Sprout, Red Chili Sauce', 5, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('049', 'Gudeg', 'جوديج', 'Sweet jackfruit curry with beef skin, egg and chicken', 'Rice, Beef Skin, Boiled Egg, Jack Fruit, Braised Chicken', 5, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('050', 'Kangkung Plecing', 'كانكونغ بليسينغ', 'Water spinach in spicy sauce served with rice', 'Rice, Morning Glory, Red Chili Sauce', 5, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('051', 'Iga Penyet', 'إيجا بينييت', 'Smashed beef ribs with rice, chili sauce and broth', 'Rice, Beef Ribs, Chili Sauce, Beef Broth', 5, 0.00, 35.00, 20, 'PCS', 5, 1, 1),

-- SAYURAN PLUS (VEGETABLES) - Category ID 6
('052', 'Terong Balado', 'باذنجان بالادو', 'Eggplant in spicy red chili sauce', 'Eggplant, Red Chili Sauce', 6, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('053', 'Terong Plus Teri Cabe Hijau', 'باذنجان مع أنشوجة والفلفل الأخضر', 'Eggplant with anchovies and green chilies', 'Eggplant, Anchovy, Green Chili', 6, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('054', 'Perkedel Kentang', 'بركديل البطاطس', 'Indonesian potato fritters with spring onions', 'Fried Potato, Spring Onion', 6, 0.00, 6.00, 20, 'PCS', 5, 1, 1),
('055', 'Sayur Kapau', 'سايور كاباو', 'Mixed vegetables with cabbage, long beans and jackfruit', 'White Cabbage, Long Beans, Jack Fruit, Spices', 6, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('056', 'Sayur Daun Singkong', 'سايور أوراق الكسافا', 'Spiced tapioca leaves curry', 'Tapioca leaves, Spices', 6, 0.00, 20.00, 20, 'PCS', 5, 1, 1),
('057', 'Sayur Urap', 'سايور أوراب', 'Mixed vegetables with spiced shredded coconut', 'Shredded Coconut, Vegetables, Spices', 6, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('058', 'Sayur Bayam', 'سايور السبانخ', 'Spiced spinach curry', 'Spinach, Spices', 6, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('059', 'Sambal Hijau', 'سامبال أخضر', 'Fresh green chili sauce with red onion', 'Green Chili, Red Onion, Oil', 6, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('060', 'Sambal Merah', 'سامبال أحمر', 'Spicy red chili sauce with tomato and onion', 'Red Chili, Tomato, Onion, Oil', 6, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('061', 'Sambal Matah', 'سامبال ماتاه', 'Balinese raw chili relish with onions and tomato', 'Green and Red Chili, Onion, Tomato', 6, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('062', 'Tempe Mendoan (3 pcs)', 'تيمبي مندوان (3 قطع)', 'Crispy battered tempeh fritters (3 pieces)', 'Tempe, Batter', 6, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('063', 'Tahu Isi (3 pcs)', 'تاهو إيسي (3 قطع)', 'Stuffed tofu with bean sprouts (3 pieces)', 'Bean Curd, Bean Sprout', 6, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('064', 'Bakwan (3 pcs)', 'باكوان (3 قطع)', 'Vegetable fritters with cabbage and carrots (3 pieces)', 'Cabbage, Bean Sprout, Carrot, Spring Onion', 6, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('065', 'Lumpia Semarang', 'لومبيا سيمارانغ', 'Semarang-style spring rolls with bamboo shoots', 'Wonton Skin, Bean Sprout, Bamboo Shoot', 6, 0.00, 12.00, 20, 'PCS', 5, 1, 1),

-- NASI (RICE) - Category ID 7
('066', 'Nasi Rames', 'ناسي راميس', 'Mixed rice with assorted gravy, chili sauces and vegetables', 'Rice, Mix Gravy, Green and Red Chili Sauce, Vegetable', 7, 0.00, 18.00, 20, 'PCS', 5, 1, 1),
('067', 'Nasi Goreng Jawa', 'ناسي جورينغ جاوي', 'Javanese-style fried rice with chicken and traditional toppings', 'Rice, Shredded Chicken, Bean Sprout, Pakchoy, Boiled Egg, Acar, Crackers', 7, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('068', 'Nasi Goreng Padang', 'ناسي جورينغ بادانغ', 'Padang-style fried rice with dendeng and spices', 'Rice, Dendeng, Fried Egg, Chili, Crackers', 7, 0.00, 28.00, 20, 'PCS', 5, 1, 1),
('069', 'Nasi Goreng Seafood', 'ناسي جورينغ مأكولات بحرية', 'Seafood fried rice with squid and shrimp', 'Rice, Squid, Shrimp, Crackers', 7, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('070', 'Nasi Goreng Ayam', 'ناسي جورينغ دجاج', 'Chicken fried rice with traditional accompaniments', 'Rice, Shredded Chicken, Boiled Egg, Acar, Crackers', 7, 0.00, 28.00, 20, 'PCS', 5, 1, 1),
('071', 'Nasi Goreng Sayuran', 'ناسي جورينغ خضروات', 'Vegetarian fried rice with fresh vegetables', 'Rice, Cabbage, Carrot, Onion, Bean Sprout, Crackers', 7, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('072', 'Nasi Goreng Kambing', 'ناسي جورينغ لحم الضأن', 'Mutton fried rice with pickled vegetables', 'Rice, Mutton, Acar, Crackers', 7, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('073', 'Nasi Goreng Petai', 'ناسي جورينغ بيتاي', 'Fried rice with bitter beans (petai)', 'Rice, Bitter Beans, Crackers', 7, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('074', 'Nasi Goreng Ikan Asin', 'ناسي جورينغ سمك مالح', 'Fried rice with salted fish and pickles', 'Rice, Salted Fish, Acar, Crackers', 7, 0.00, 28.00, 20, 'PCS', 5, 1, 1),

-- MIE (NOODLES) - Category ID 8
('075', 'Mie Ayam Pangsit', 'مي أيام بانسيت', 'Chicken noodles with fried wonton and braised chicken', 'Noodles, Fried Wonton Skin, Braised Minced Chicken', 8, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('076', 'Mie Goreng Ayam', 'مي جورينغ دجاج', 'Fried noodles with chicken and vegetables', 'Noodles, Shredded Chicken, Cabbage, Acar, Crackers', 8, 0.00, 22.00, 20, 'PCS', 5, 1, 1),
('077', 'Mie Goreng Seafood', 'مي جورينغ مأكولات بحرية', 'Seafood fried noodles with squid and shrimp', 'Noodles, Squid, Shrimp, Acar, Crackers', 8, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('078', 'Mie Ayam Bakso', 'مي أيام باكسو', 'Chicken noodles with meatballs and wonton', 'Noodles, Braised Minced Chicken, Fried Wonton Skin, Beef Meat Balls', 8, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('079', 'Mie Capcay Kuah', 'مي كابتشاي كواه', 'Noodle soup with mixed vegetables and chicken', 'Noodles, Cabbage, Shredded Chicken, Carrot, Fish Cake, Cauliflowers, Pakchoy', 8, 0.00, 25.00, 20, 'PCS', 5, 1, 1),

-- SOTO PLUS SUP (SOUPS) - Category ID 9
('080', 'Soto Padang', 'سوتو بادانغ', 'Traditional Padang soup with beef, glass noodles and rice', 'Rice, Dendeng, Glass noodles, Potato, Spring Onion, Beef broth, Crackers', 9, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('081', 'Soto Ayam Lamongan', 'سوتو أيام لامونجان', 'Lamongan-style chicken soup with rice and traditional toppings', 'Rice, Chicken broth, Shredded Chicken, Boiled Egg, Glass Noodles, Cabbage, Chili Sauce', 9, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('082', 'Soto Mie', 'سوتو مي', 'Noodle soup with beef cubes and vegetables', 'Rice, Noodles, Beef Cube, Potato, Crackers, Beef broth, Spring rolls', 9, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('083', 'Soto Betawi', 'سوتو بيتاوي', 'Jakarta-style beef soup with coconut milk and vegetables', 'Rice, Beef Cube, Potato, Crackers, Tomato, Beef broth, Coconut milk', 9, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('084', 'Soto Bandung', 'سوتو باندونغ', 'Bandung-style beef soup with radish and soy beans', 'Rice, Beef broth, Beef Cube, Radish, Soya Bean, Chili Sauce', 9, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('085', 'Sop Iga Jumbo', 'شوربة إيجا جامبو', 'Jumbo beef ribs soup with mixed vegetables', 'Rice, Beef Ribs, Potato, Carrot, Tomato, Beef broth', 9, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('086', 'Sop Buntut Kuah', 'شوربة ذيل البقر', 'Oxtail soup in clear broth with vegetables', 'Rice, Oxtail, Beef broth, Carrot, Potato, Tomato', 9, 0.00, 35.00, 20, 'PCS', 5, 1, 1),
('087', 'Sop Buntut Goreng', 'شوربة ذيل البقر المقلي', 'Fried oxtail soup with rich beef broth', 'Rice, Oxtail fried, Beef broth', 9, 0.00, 35.00, 20, 'PCS', 5, 1, 1),

-- SATE (SATAY/GRILLED) - Category ID 10
('088', 'Sate Padang Daging (5 pcs)', 'سيخ شواء بادانغ لحم (5 قطع)', 'Padang-style beef satay with rice cake and special sauce (5 pieces)', 'Rice Cake, Beef Satay, Sauce Padang', 10, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('089', 'Sate Kambing (5 pcs)', 'سيخ شواء لحم الضأن (5 قطع)', 'Grilled mutton satay with sweet soy sauce (5 pieces)', 'Mutton Satay, Sweet Soya Sauce', 10, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('090', 'Sate Ayam (5 pcs)', 'سيخ شواء دجاج (5 قطع)', 'Chicken satay with peanut sauce (5 pieces)', 'Chicken Satay, Peanut Sauce', 10, 0.00, 25.00, 20, 'PCS', 5, 1, 1),
('091', 'Sate Lilit Ayam (5 pcs)', 'سيخ شواء ليليت دجاج (5 قطع)', 'Balinese-style minced chicken satay with sambal matah (5 pieces)', 'Minced Chicken Satay, Sambel Matah', 10, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('092', 'Sate Maranggi (5 pcs)', 'سيخ شواء مارانجي (5 قطع)', 'Maranggi-style beef satay with special sauce (5 pieces)', 'Beef Satay, Maranggi Sauce', 10, 0.00, 30.00, 20, 'PCS', 5, 1, 1),
('093', 'Sate Padang Lidah (5 pcs)', 'سيخ شواء بادانغ لسان (5 قطع)', 'Premium beef tongue satay with rice cake and Padang sauce (5 pieces)', 'Rice Cake, Beef Tongue Satay, Sauce Padang', 10, 0.00, 35.00, 20, 'PCS', 5, 1, 1),

-- MINUMAN (BEVERAGES) - Category ID 11
('094', 'Tea Tawar', 'شاي بدون سكر', 'Plain black tea without sugar', 'Plain Tea Water', 11, 0.00, 3.00, 20, 'PCS', 5, 1, 1),
('095', 'Ice Tea Manis', 'شاي مثلج محلى', 'Sweet iced tea with sugar', 'Sweetened Tea Water', 11, 0.00, 4.00, 20, 'PCS', 5, 1, 1),
('096', 'Lemonade/Es Jeruk Nipis', 'عصير ليمون/عصير ليمون مثلج', 'Fresh lemonade with sweetened lime water', 'Sweetened Lime Water', 11, 0.00, 7.00, 20, 'PCS', 5, 1, 1),
('097', 'Es Serai', 'عصير الليمون العشبي', 'Refreshing lemongrass iced tea', 'Lemongrass Tea', 11, 0.00, 7.00, 20, 'PCS', 5, 1, 1),
('098', 'Wedang Jahe', 'مشروب الزنجبيل', 'Traditional warm ginger drink', 'Sweetened Ginger Water', 11, 0.00, 8.00, 20, 'PCS', 5, 1, 1),
('099', 'Es Longan/Lychee', 'عصير لونجان/ليتشي مثلج', 'Sweet longan or lychee iced tea', 'Sweetened lychee tea', 11, 0.00, 8.00, 20, 'PCS', 5, 1, 1),
('100', 'Soft Drink', 'مشروبات غازية', 'Assorted carbonated soft drinks', '', 11, 0.00, 4.00, 20, 'PCS', 5, 1, 1),
('101', 'Mineral Water', 'مياه معدنية', 'Pure bottled mineral water', '', 11, 0.00, 2.00, 20, 'PCS', 5, 1, 1),
('102', 'Es Milo', 'مشروب ميلو مثلج', 'Iced chocolate malt drink', '', 11, 0.00, 6.00, 20, 'PCS', 5, 1, 1),
('103', 'Es Jeruk Cincau', 'عصير برتقال مع العشب الأسود', 'Orange syrup with grass jelly, basil seeds and sprite', 'Orange Syrup, Cincau, Basil Seeds, Sprite', 11, 0.00, 10.00, 20, 'PCS', 5, 1, 1),

-- DESSERTS PLUS - Category ID 12
('104', 'Pudding Vla', 'بودينغ فلا', 'Creamy pudding served with vanilla sauce', 'Puddings, Vla Sauce', 12, 0.00, 10.00, 20, 'PCS', 5, 1, 1),
('105', 'Bolu Kukus', 'كيك بولو مطبوخ بالبخار', 'Traditional Indonesian steamed sponge cakes', 'Steamed Cakes', 12, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('106', 'Martabak Manis', 'مرطبك حلو', 'Sweet thick pancake with peanut, cheese and chocolate', 'Pancakes, Peanut, Cheese, Chocolate Granules', 12, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('107', 'Dadar Gulung', 'داهدار جولونغ', 'Green pandan crepes filled with sweetened coconut', 'Crepes, Sweetened Coconut', 12, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('108', 'Kue Putri Ayu', 'كيك بوتري أيو', 'Traditional steamed cakes topped with grated coconut', 'Steamed Cakes, Grated Coconut', 12, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('109', 'Es Cendol', 'عصير سيندول مثلج', 'Traditional iced dessert with rice flour jelly and coconut milk', 'Rice Flour Jelly, Coconut Milk, Palm Sugar Syrup', 12, 0.00, 10.00, 20, 'PCS', 5, 1, 1),
('110', 'Es Cincau', 'عصير العشب الأسود مثلج', 'Refreshing grass jelly dessert with coconut milk', 'Grass Jelly, Coconut Milk, Palm Sugar Syrup', 12, 0.00, 10.00, 20, 'PCS', 5, 1, 1),
('111', 'Bubur Kampiun', 'بوبور كامبيون', 'Black sticky rice porridge with banana and rice flour jelly', 'Black Sticky Rice, Banana, Cendil, Rice Flour Jelly', 12, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('112', 'Es Campur', 'عصير مختلط مثلج', 'Mixed ice dessert with tropical fruits and syrup', 'Jack Fruit, Palm fruit, Young Coconut, Red Syrup, Sweetened Milk, Grated Ice', 12, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('113', 'Es Teller', 'عصير تيلر مثلج', 'Premium mixed ice dessert with avocado and coconut', 'Avocado, Grass Jelly, Jack Fruit, Shaved Young Coconut, Coconut milk, Condensed Milk', 12, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('114', 'Klepon', 'كليبون', 'Traditional rice cake balls filled with palm sugar', 'Rice Cake Balls, Palm Sugar, Grated Coconut', 12, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('115', 'Wedang Jahe', 'ويدانغ جاهي', 'Warm ginger drink with glutinous rice balls and peanuts', 'Glutinous Rice Cake Balls, Peanut, Shaved Coconut, Sweetened Ginger Water', 12, 0.00, 12.00, 20, 'PCS', 5, 1, 1),
('116', 'Sekoteng', 'سيكوتينغ', 'Traditional warm dessert drink with tapioca pearls and nuts', 'Tapioca Pearl, Peanut, Mung Beans, Bread Cubes, Ginger Sweetened Ginger Water', 12, 0.00, 12.00, 20, 'PCS', 5, 1, 1),

-- KIDS - Category ID 13
('117', 'Chicken Steak Meals', 'وجبة ستيك الدجاج', 'Kid-friendly chicken steak with mashed potato and mixed vegetables', 'Chicken, Mashed Potato, Mixed Veggie, Gravy', 13, 0.00, 20.00, 20, 'PCS', 5, 1, 1),
('118', 'Chicken Nuggets Meals', 'وجبة قطع الدجاج المقلية', 'Crispy chicken nuggets served with french fries', 'Chicken Nuggets, French Fries', 13, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('119', 'Meat Balls Meals', 'وجبة كرات اللحم', 'Tender meatballs with mashed potato and mixed vegetables', 'Meat Balls, Mashed Potato, Mixed Veggie, Gravy', 13, 0.00, 20.00, 20, 'PCS', 5, 1, 1),
('120', 'Spaghetti With Tomato Sauce', 'سباغيتي بصوص الطماطم', 'Classic spaghetti pasta with tomato sauce', 'Spaghetti, Tomato Sauce', 13, 0.00, 15.00, 20, 'PCS', 5, 1, 1),
('121', 'Sausage Meals', 'وجبة النقانق', 'Grilled sausages served with crispy french fries', 'Sausage, French Fries', 13, 0.00, 15.00, 20, 'PCS', 5, 1, 1);

-- ===================================================================

-- Total Categories: 13
-- Total Products: 121
-- All products have initial stock of 20 units
-- All products are enabled for POS
-- All Arabic translations included

-- ===================================================================