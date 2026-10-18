<?php

   namespace App\Services;

   /**
    * Hard-coded list of well-known TESDA-style courses and the career paths they lead to.
    * These are SUGGESTIONS only. Whether Maxima actually offers a course is decided
    * by the training_programs table, never by this list.
    *
    *  strong   words that name the trade or career (worth 3 points)
    *  keywords softer clues: interests, traits, goals (worth 1 point)
    *  aliases  words a real course TITLE would contain; every word in one alias
    *           must be in the title ([['food','beverage']] needs both)
    *  related  keys of courses in the same field, used to complete a set of three
    *
    * Names follow TESDA's usual titles, so check them against the official list.
    */
   class CourseCatalog
   {
      public static function all(): array
      {
         return [
               // ---------- business & communication ----------
               'sales' => [
                  'name' => 'Sales and Customer Service',
                  'careers' => ['Sales associate', 'Customer service staff', 'Marketing assistant'],
                  'strong' => ['sales', 'salesman', 'saleslady', 'marketing', 'retail'],
                  'keywords' => ['selling', 'sell', 'customer', 'customers', 'persuasive', 'convincing', 'negotiate', 'negotiation', 'talkative', 'communication', 'communicator', 'communicate', 'communicating', 'communicative', 'friendly', 'outgoing', 'sociable', 'confident', 'speaker', 'store'],
                  'aliases' => [['sales'], ['marketing'], ['retail'], ['customer', 'service']],
                  'related' => ['contact_center', 'entrepreneurship', 'front_office'],
               ],
               'contact_center' => [
                  'name' => 'Contact Center Services NC II',
                  'careers' => ['Call center agent', 'Customer support representative'],
                  'strong' => ['call', 'callcenter', 'bpo', 'telemarketer'],
                  'keywords' => ['customer', 'customers', 'support', 'english', 'communication', 'communicator', 'communicate', 'communicating', 'communicative', 'talkative', 'friendly', 'polite', 'patient', 'convincing', 'speaking', 'phone', 'listening'],
                  'aliases' => [['contact', 'center']],
                  'related' => ['sales', 'front_office', 'tourism'],
               ],
               'entrepreneurship' => [
                  'name' => 'Entrepreneurship and Small Business Livelihood',
                  'careers' => ['Small business owner', 'Entrepreneur'],
                  'strong' => ['entrepreneur', 'entrepreneurship', 'negosyo', 'livelihood', 'business'],
                  'keywords' => ['tindahan', 'store', 'sari', 'sell', 'selling', 'vendor', 'income', 'earn', 'earning', 'financially', 'financial', 'successful', 'success', 'rich', 'wealth', 'profit', 'capital', 'boss', 'owner', 'independent', 'savings'],
                  'aliases' => [['entrepreneurship'], ['small', 'business'], ['business', 'management']],
                  'related' => ['bookkeeping', 'sales', 'food_processing'],
               ],
               'bookkeeping' => [
                  'name' => 'Bookkeeping NC III',
                  'careers' => ['Bookkeeper', 'Accounting clerk'],
                  'strong' => ['bookkeeper', 'bookkeeping', 'accounting', 'accountant'],
                  'keywords' => ['numbers', 'math', 'mathematics', 'money', 'cashier', 'budget', 'budgeting', 'records', 'organized', 'honest', 'detail', 'details', 'careful', 'accurate'],
                  'aliases' => [['bookkeeping'], ['accounting']],
                  'related' => ['entrepreneurship', 'sales', 'contact_center'],
               ],

               // ---------- hospitality & tourism ----------
               'front_office' => [
                  'name' => 'Front Office Services NC II',
                  'careers' => ['Receptionist', 'Front desk agent'],
                  'strong' => ['receptionist', 'reception', 'frontdesk'],
                  'keywords' => ['front', 'desk', 'hotel', 'guest', 'guests', 'welcoming', 'friendly', 'polite', 'communication', 'communicator', 'communicate', 'talkative', 'outgoing', 'sociable', 'pleasant', 'english'],
                  'aliases' => [['front', 'office']],
                  'related' => ['tourism', 'contact_center', 'housekeeping'],
               ],
               'tourism' => [
                  'name' => 'Tourism Promotion Services NC II',
                  'careers' => ['Tour guide', 'Travel and tourism staff'],
                  'strong' => ['tourism', 'tourist', 'tourists', 'guide', 'travel'],
                  'keywords' => ['tour', 'history', 'historical', 'places', 'beach', 'heritage', 'communication', 'communicate', 'communicator', 'speaker', 'speaking', 'outgoing', 'friendly', 'confident', 'english'],
                  'aliases' => [['tourism']],
                  'related' => ['front_office', 'events', 'housekeeping'],
               ],
               'housekeeping' => [
                  'name' => 'Housekeeping NC II',
                  'careers' => ['Room attendant', 'Housekeeper', 'Hotel cleaner'],
                  'strong' => ['housekeeping', 'housekeeper', 'janitor', 'linen'],
                  'keywords' => ['hotel', 'cleaning', 'clean', 'tidy', 'neat', 'organized', 'room', 'rooms', 'resort', 'laundry'],
                  'aliases' => [['housekeeping']],
                  'related' => ['front_office', 'food_bev', 'tourism'],
               ],
               'events' => [
                  'name' => 'Events Management Services NC III',
                  'careers' => ['Events coordinator', 'Event organizer'],
                  'strong' => ['event', 'events', 'organizer', 'wedding', 'weddings'],
                  'keywords' => ['party', 'parties', 'planning', 'plan', 'coordinator', 'coordinate', 'leader', 'leadership', 'organized', 'organizing', 'creative', 'responsible', 'decor'],
                  'aliases' => [['events']],
                  'related' => ['tourism', 'food_bev', 'front_office'],
               ],

               // ---------- food & beverage ----------
               'cookery' => [
                  'name' => 'Cookery NC II',
                  'careers' => ['Cook', 'Chef', 'Kitchen staff'],
                  'strong' => ['cook', 'cooking', 'cookery', 'chef', 'culinary', 'magluto', 'luto'],
                  'keywords' => ['kitchen', 'food', 'recipe', 'recipes', 'dish', 'dishes', 'restaurant', 'carinderia', 'meals', 'ingredients'],
                  'aliases' => [['cookery'], ['culinary']],
                  'related' => ['bread_pastry', 'food_bev', 'food_processing'],
               ],
               'bread_pastry' => [
                  'name' => 'Bread and Pastry Production NC II',
                  'careers' => ['Baker', 'Pastry cook'],
                  'strong' => ['bake', 'baking', 'baker', 'bread', 'pastry', 'panadero', 'tinapay'],
                  'keywords' => ['cake', 'cakes', 'cupcake', 'dessert', 'desserts', 'oven', 'bakery', 'pandesal'],
                  'aliases' => [['bread'], ['pastry'], ['baking']],
                  'related' => ['cookery', 'food_processing', 'food_bev'],
               ],
               'bartending' => [
                  'name' => 'Bartending NC II',
                  'careers' => ['Bartender', 'Bar staff'],
                  'strong' => ['bartender', 'bartending', 'mixology', 'cocktail', 'cocktails'],
                  'keywords' => ['bar', 'drinks', 'drink', 'beverage', 'beverages', 'nightlife', 'mixing'],
                  'aliases' => [['bartending'], ['bartender']],
                  'related' => ['barista', 'food_bev', 'events'],
               ],
               'barista' => [
                  'name' => 'Barista NC II',
                  'careers' => ['Barista', 'Coffee shop staff'],
                  'strong' => ['barista', 'coffee', 'espresso'],
                  'keywords' => ['cafe', 'drinks', 'drink', 'beverage', 'beverages', 'latte', 'milktea'],
                  'aliases' => [['barista'], ['coffee']],
                  'related' => ['bartending', 'food_bev', 'cookery'],
               ],
               'food_bev' => [
                  'name' => 'Food and Beverage Services NC II',
                  'careers' => ['Waiter / Food server', 'Restaurant staff'],
                  'strong' => ['waiter', 'waitress', 'server', 'serving', 'dining'],
                  'keywords' => ['restaurant', 'cafe', 'food', 'beverage', 'customer', 'customers', 'guests', 'polite', 'service'],
                  'aliases' => [['food', 'beverage']],
                  'related' => ['bartending', 'barista', 'cookery'],
               ],
               'food_processing' => [
                  'name' => 'Food Processing NC II',
                  'careers' => ['Food processor', 'Small food producer'],
                  'strong' => ['preserve', 'preserving', 'processing', 'longganisa', 'tocino', 'tapa'],
                  'keywords' => ['dried', 'meat', 'jam', 'pickles', 'sauces', 'food', 'products'],
                  'aliases' => [['food', 'processing']],
                  'related' => ['bread_pastry', 'cookery', 'food_bev'],
               ],

               // ---------- automotive & transport ----------
               'automotive' => [
                  'name' => 'Automotive Servicing NC II',
                  'careers' => ['Auto mechanic', 'Automotive technician'],
                  'strong' => ['automotive', 'mechanic', 'mekaniko', 'automobile'],
                  'keywords' => ['vehicle', 'vehicles', 'engine', 'engines', 'garage', 'tuneup', 'makina'],
                  'aliases' => [['automotive']],
                  'related' => ['motorcycle', 'driving', 'heavy_equipment'],
               ],
               'motorcycle' => [
                  'name' => 'Motorcycle/Small Engine Servicing NC II',
                  'careers' => ['Motorcycle mechanic', 'Small engine technician'],
                  'strong' => ['motorcycle', 'motorcycles', 'scooter'],
                  'keywords' => ['motor', 'engine', 'engines', 'mechanic', 'mekaniko', 'tricycle'],
                  'aliases' => [['motorcycle'], ['small', 'engine']],
                  'related' => ['automotive', 'driving', 'electrical'],
               ],
               'driving' => [
                  'name' => 'Driving NC II',
                  'careers' => ['Driver', 'Delivery driver'],
                  'strong' => ['driver', 'driving', 'drive', 'jeepney', 'tricycle'],
                  'keywords' => ['truck', 'van', 'delivery', 'license', 'vehicle', 'vehicles', 'roads', 'passengers', 'habal'],
                  'aliases' => [['driving']],
                  'related' => ['automotive', 'motorcycle', 'heavy_equipment'],
               ],
               'heavy_equipment' => [
                  'name' => 'Heavy Equipment Operation NC II',
                  'careers' => ['Heavy equipment operator'],
                  'strong' => ['excavator', 'bulldozer', 'backhoe', 'crane', 'forklift'],
                  'keywords' => ['heavy', 'equipment', 'operator', 'machinery', 'machines'],
                  'aliases' => [['heavy', 'equipment']],
                  'related' => ['driving', 'automotive', 'motorcycle'],
               ],

               // ---------- electrical, electronics, computers ----------
               'electrical' => [
                  'name' => 'Electrical Installation and Maintenance NC II',
                  'careers' => ['Electrician', 'Electrical installer'],
                  'strong' => ['electrician', 'electrical', 'electric', 'electricity', 'kuryente', 'wiring'],
                  'keywords' => ['wire', 'wires', 'circuit', 'circuits', 'installation', 'bulbs', 'power', 'tinkering', 'appliance'],
                  'aliases' => [['electrical']],
                  'related' => ['electronics', 'rac', 'computer_servicing'],
               ],
               'electronics' => [
                  'name' => 'Consumer Electronics Servicing NC II',
                  'careers' => ['Electronics technician', 'Appliance repair technician'],
                  'strong' => ['electronics', 'electronic', 'appliance', 'appliances', 'television', 'radio'],
                  'keywords' => ['gadget', 'gadgets', 'cellphone', 'phone', 'phones', 'tinkering', 'devices'],
                  'aliases' => [['consumer', 'electronics'], ['electronics']],
                  'related' => ['computer_servicing', 'electrical', 'rac'],
               ],
               'rac' => [
                  'name' => 'Refrigeration and Airconditioning Servicing NC II',
                  'careers' => ['Aircon technician', 'Refrigeration technician'],
                  'strong' => ['aircon', 'airconditioning', 'refrigerator', 'refrigeration', 'freezer'],
                  'keywords' => ['cooling', 'ref', 'installation', 'electrical'],
                  'aliases' => [['refrigeration'], ['airconditioning'], ['air', 'conditioning'], ['rac']],
                  'related' => ['electrical', 'electronics', 'automotive'],
               ],
               'computer_servicing' => [
                  'name' => 'Computer Systems Servicing NC II',
                  'careers' => ['Computer technician', 'IT support'],
                  'strong' => ['computer', 'computers', 'laptop', 'laptops', 'kompyuter', 'hardware'],
                  'keywords' => ['technology', 'tech', 'gadget', 'gadgets', 'cellphone', 'phone', 'internet', 'networking', 'tinkering', 'troubleshoot', 'troubleshooting'],
                  'aliases' => [['computer', 'systems'], ['computer', 'hardware'], ['computer', 'servicing']],
                  'related' => ['electronics', 'web_dev', 'electrical'],
               ],
               'web_dev' => [
                  'name' => 'Web Development NC III',
                  'careers' => ['Web developer', 'Programmer'],
                  'strong' => ['programmer', 'programming', 'coding', 'code', 'developer', 'website', 'websites'],
                  'keywords' => ['software', 'web', 'app', 'apps', 'internet', 'logic', 'puzzles', 'problem', 'solving', 'computer', 'online', 'technology'],
                  'aliases' => [['web', 'development'], ['programming'], ['software']],
                  'related' => ['computer_servicing', 'graphic_design', 'animation'],
               ],

               // ---------- creative ----------
               'graphic_design' => [
                  'name' => 'Visual Graphic Design NC III',
                  'careers' => ['Graphic designer', 'Layout artist'],
                  'strong' => ['graphic', 'graphics', 'designer', 'design', 'illustration', 'photoshop'],
                  'keywords' => ['drawing', 'draw', 'drawings', 'art', 'artist', 'artistic', 'creative', 'creativity', 'poster', 'posters', 'layout', 'edit', 'editing', 'sketch', 'colors', 'logo'],
                  'aliases' => [['graphic']],
                  'related' => ['animation', 'photography', 'web_dev'],
               ],
               'animation' => [
                  'name' => '2D Animation NC III',
                  'careers' => ['Animator', '2D artist'],
                  'strong' => ['animation', 'animator', 'cartoon', 'cartoons', 'anime'],
                  'keywords' => ['drawing', 'draw', 'artistic', 'artist', 'creative', 'motion', 'video', 'videos', 'characters', 'sketch'],
                  'aliases' => [['animation']],
                  'related' => ['graphic_design', 'photography', 'web_dev'],
               ],
               'photography' => [
                  'name' => 'Photography NC II',
                  'careers' => ['Photographer', 'Videographer'],
                  'strong' => ['photography', 'photographer', 'camera', 'videography', 'videographer'],
                  'keywords' => ['photo', 'photos', 'video', 'videos', 'vlog', 'vlogging', 'edit', 'editing', 'creative', 'portraits'],
                  'aliases' => [['photography'], ['videography'], ['video', 'production']],
                  'related' => ['graphic_design', 'animation', 'events'],
               ],

               // ---------- health & wellness ----------
               'caregiving' => [
                  'name' => 'Caregiving NC II',
                  'careers' => ['Caregiver', 'Elderly care assistant'],
                  'strong' => ['caregiver', 'caregiving', 'elderly', 'alaga', 'katulong'],
                  'keywords' => ['patient', 'patients', 'nurse', 'nursing', 'care', 'sick', 'lola', 'lolo', 'grandparents', 'compassionate', 'nurturing', 'kindhearted', 'helpful', 'abroad', 'overseas', 'ofw', 'children', 'kids'],
                  'aliases' => [['caregiving'], ['caregiver']],
                  'related' => ['health_care', 'massage', 'housekeeping'],
               ],
               'health_care' => [
                  'name' => 'Health Care Services NC II',
                  'careers' => ['Health care assistant', 'Nursing aide'],
                  'strong' => ['health', 'medical', 'hospital', 'clinic', 'nursing', 'nurse'],
                  'keywords' => ['patient', 'patients', 'first', 'aid', 'care', 'sick', 'medicine', 'doctor', 'healthcare', 'helpful', 'compassionate', 'abroad', 'overseas'],
                  'aliases' => [['health', 'care'], ['healthcare']],
                  'related' => ['caregiving', 'massage', 'beauty_care'],
               ],
               'massage' => [
                  'name' => 'Massage Therapy NC II',
                  'careers' => ['Massage therapist', 'Wellness / spa therapist'],
                  'strong' => ['massage', 'masseur', 'masahista', 'hilot'],
                  'keywords' => ['therapy', 'therapist', 'wellness', 'spa', 'relax', 'relaxing', 'healing', 'touch', 'pain', 'muscles'],
                  'aliases' => [['massage']],
                  'related' => ['health_care', 'caregiving', 'beauty_care'],
               ],

               // ---------- personal services & garments ----------
               'hairdressing' => [
                  'name' => 'Hairdressing NC II',
                  'careers' => ['Hairdresser', 'Salon stylist'],
                  'strong' => ['hairdresser', 'hairdressing', 'haircut', 'haircuts', 'hair', 'salon', 'stylist'],
                  'keywords' => ['styling', 'gupit', 'beauty', 'rebond', 'hairstyle', 'hairstyles', 'coloring'],
                  'aliases' => [['hairdressing']],
                  'related' => ['barbering', 'beauty_care', 'massage'],
               ],
               'barbering' => [
                  'name' => 'Barbering NC II',
                  'careers' => ['Barber', 'Barbershop owner'],
                  'strong' => ['barber', 'barbering', 'barbero', 'barbershop'],
                  'keywords' => ['haircut', 'haircuts', 'gupit', 'shave', 'shaving', 'grooming', 'fade'],
                  'aliases' => [['barbering']],
                  'related' => ['hairdressing', 'beauty_care', 'massage'],
               ],
               'beauty_care' => [
                  'name' => 'Beauty Care Services (Nail Care) NC II',
                  'careers' => ['Nail technician', 'Beautician', 'Makeup artist'],
                  'strong' => ['nail', 'nails', 'manicure', 'pedicure', 'beautician', 'makeup', 'cosmetology'],
                  'keywords' => ['beauty', 'cosmetic', 'cosmetics', 'skin', 'skincare', 'facial', 'salon', 'pretty', 'grooming'],
                  'aliases' => [['beauty', 'care'], ['nail', 'care'], ['cosmetology']],
                  'related' => ['hairdressing', 'massage', 'barbering'],
               ],
               'dressmaking' => [
                  'name' => 'Dressmaking NC II',
                  'careers' => ['Dressmaker', 'Seamstress / seamster'],
                  'strong' => ['sew', 'sewing', 'dressmaker', 'dressmaking', 'seamstress', 'mananahi', 'tahi', 'modista'],
                  'keywords' => ['clothes', 'clothing', 'dress', 'dresses', 'fashion', 'fabric', 'fabrics', 'needle', 'pattern', 'patterns', 'designs'],
                  'aliases' => [['dressmaking']],
                  'related' => ['tailoring', 'entrepreneurship', 'bookkeeping'],
               ],
               'tailoring' => [
                  'name' => 'Tailoring NC II',
                  'careers' => ['Tailor', 'Alteration specialist'],
                  'strong' => ['tailor', 'tailoring', 'alterations', 'alteration'],
                  'keywords' => ['sew', 'sewing', 'suit', 'suits', 'uniform', 'uniforms', 'pattern', 'patterns', 'fabric', 'fabrics', 'clothes', 'clothing'],
                  'aliases' => [['tailoring']],
                  'related' => ['dressmaking', 'entrepreneurship', 'bookkeeping'],
               ],

               // ---------- agriculture & fishery ----------
               'crops' => [
                  'name' => 'Agricultural Crops Production NC II',
                  'careers' => ['Farmer', 'Crop grower'],
                  'strong' => ['farm', 'farmer', 'farming', 'crop', 'crops', 'magsasaka', 'agriculture'],
                  'keywords' => ['rice', 'palay', 'corn', 'vegetable', 'vegetables', 'garden', 'gardening', 'plants', 'planting', 'harvest', 'bukid', 'soil', 'coconut'],
                  'aliases' => [['crop'], ['agriculture']],
                  'related' => ['animal', 'aquaculture', 'entrepreneurship'],
               ],
               'animal' => [
                  'name' => 'Animal Production NC II',
                  'careers' => ['Livestock / poultry raiser'],
                  'strong' => ['poultry', 'chicken', 'chickens', 'swine', 'pig', 'pigs', 'hog', 'livestock', 'baboy', 'manok'],
                  'keywords' => ['animal', 'animals', 'goat', 'goats', 'cattle', 'cow', 'cows', 'carabao', 'feeding', 'raising', 'farm', 'ducks'],
                  'aliases' => [['animal', 'production'], ['poultry'], ['swine'], ['livestock']],
                  'related' => ['crops', 'aquaculture', 'entrepreneurship'],
               ],
               'aquaculture' => [
                  'name' => 'Aquaculture NC II',
                  'careers' => ['Fish farmer', 'Fisherfolk'],
                  'strong' => ['fish', 'fishing', 'fisherman', 'fisherfolk', 'fishpond', 'tilapia', 'bangus', 'aquaculture', 'isda'],
                  'keywords' => ['pond', 'sea', 'boat', 'boats', 'seafood', 'shrimp', 'crabs'],
                  'aliases' => [['aquaculture'], ['fish', 'culture']],
                  'related' => ['crops', 'animal', 'food_processing'],
               ],

               // ---------- construction & metalwork ----------
               'carpentry' => [
                  'name' => 'Carpentry NC II',
                  'careers' => ['Carpenter', 'Furniture maker'],
                  'strong' => ['carpenter', 'carpentry', 'woodwork', 'woodworking', 'karpintero', 'kahoy', 'furniture'],
                  'keywords' => ['wood', 'cabinet', 'cabinets', 'hammer', 'building', 'construction', 'build', 'framing', 'doors'],
                  'aliases' => [['carpentry']],
                  'related' => ['masonry', 'tile', 'painting'],
               ],
               'masonry' => [
                  'name' => 'Masonry NC II',
                  'careers' => ['Mason', 'Bricklayer'],
                  'strong' => ['mason', 'masonry', 'bricklaying', 'bricklayer', 'concrete', 'cement'],
                  'keywords' => ['brick', 'bricks', 'blocks', 'hollowblocks', 'construction', 'building', 'build', 'plaster', 'plastering', 'foundation', 'laborer'],
                  'aliases' => [['masonry']],
                  'related' => ['carpentry', 'tile', 'plumbing'],
               ],
               'plumbing' => [
                  'name' => 'Plumbing NC II',
                  'careers' => ['Plumber', 'Pipefitter'],
                  'strong' => ['plumber', 'plumbing', 'tubero', 'pipefitting', 'pipes', 'pipe'],
                  'keywords' => ['faucet', 'faucets', 'water', 'leak', 'leaks', 'drainage'],
                  'aliases' => [['plumbing']],
                  'related' => ['masonry', 'carpentry', 'welding'],
               ],
               'tile' => [
                  'name' => 'Tile Setting NC II',
                  'careers' => ['Tile setter'],
                  'strong' => ['tile', 'tiles', 'tiling', 'tiler'],
                  'keywords' => ['floor', 'floors', 'flooring', 'finishing', 'bathroom'],
                  'aliases' => [['tile'], ['tiles']],
                  'related' => ['masonry', 'painting', 'carpentry'],
               ],
               'painting' => [
                  'name' => 'Construction Painting NC II',
                  'careers' => ['Painter'],
                  'strong' => ['painter', 'painting', 'paint', 'paints'],
                  'keywords' => ['wall', 'walls', 'brush', 'colors', 'finishing'],
                  'aliases' => [['painting']],
                  'related' => ['tile', 'masonry', 'carpentry'],
               ],
               'welding' => [
                  'name' => 'Shielded Metal Arc Welding (SMAW) NC II',
                  'careers' => ['Welder', 'Metal fabricator'],
                  'strong' => ['welder', 'welding', 'weld', 'smaw', 'hinang', 'fabrication'],
                  'keywords' => ['metal', 'steel', 'iron', 'torch', 'sparks', 'gates', 'grills', 'shipyard', 'abroad', 'overseas', 'ofw'],
                  'aliases' => [['welding'], ['smaw'], ['shielded', 'metal']],
                  'related' => ['machining', 'plumbing', 'automotive'],
               ],
               'machining' => [
                  'name' => 'Machining NC II',
                  'careers' => ['Machinist', 'Machine operator'],
                  'strong' => ['machinist', 'machining', 'lathe', 'milling'],
                  'keywords' => ['machine', 'machines', 'metalworking', 'precision', 'metal', 'steel', 'factory', 'measurement', 'blueprint'],
                  'aliases' => [['machining']],
                  'related' => ['welding', 'automotive', 'electrical'],
               ],
         ];
      }
   }