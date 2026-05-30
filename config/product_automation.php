<?php

return [
    /*
     * Keywords that indicate a buyer is asking for product images/photos.
     * Add or remove freely — this list is checked against incoming messages.
     */
    'image_intent_keywords' => [
        // English
        'picture', 'photo', 'image', 'img', 'pic', 'photos', 'pictures', 'images',
        'show me', 'see it', 'see the', 'more photo', 'aro photo', 'aro picture',
        'another photo', 'other photo',

        // Bengali (romanised)
        'ছবি', 'পিক', 'ফটো', 'দেখান', 'দেখতে চাই', 'দেখাও', 'দেখাবেন',
        'picture den', 'photo den', 'chhobi den', 'pic den',
        'ar picture', 'ar photo', 'aro chhobi', 'aro pic',

        // Product availability / inquiry
        'available', 'ache', 'আছে', 'stock', 'আছে কি', 'পাবো', 'পাওয়া যাবে',
    ],

    /*
     * Keywords that indicate a follow-up about PRICE on the last product.
     */
    'price_intent_keywords' => [
        'price', 'cost', 'rate', 'how much', 'দাম', 'মূল্য', 'কত', 'কত টাকা', 'dam koto',
        'price koto', 'daam', 'koto dam',
    ],

    /*
     * Keywords that indicate a follow-up for more images of the SAME product.
     */
    'more_images_keywords' => [
        'more', 'another', 'aro', 'আরো', 'আর', 'etar aro', 'এটার আরো', 'other side',
        'different angle', 'front', 'back', 'side',
    ],

    /*
     * Bengali stop-words and filler phrases to strip before product keyword extraction.
     */
    'strip_words' => [
        'আমাকে', 'আপনাকে', 'একটু', 'দয়া করে', 'ভাই', 'আপু', 'দাদা', 'please',
        'kindly', 'আছে', 'কি', 'আছে কি', 'den', 'দেন', 'পাঠান',
    ],

    /*
     * Maximum products to return in a single reply.
     * Users can override per-source via max_images_per_reply on the ProductSource.
     */
    'default_max_images' => 4,

    /*
     * How many hours to keep conversation product context in the DB.
     */
    'context_ttl_hours' => 24,

    /*
     * Comment reply text when sending images to Messenger.
     */
    'comment_redirect_text' => 'আমি আপনাকে ইনবক্সে ছবিগুলো পাঠিয়ে দিচ্ছি। 📸',

    /*
     * Fallback reply when no product images are found.
     */
    'no_product_fallback' => 'দুঃখিত, এই প্রোডাক্টের ছবি এখন খুঁজে পাওয়া যাচ্ছে না। আপনি চাইলে আমাদের ওয়েবসাইট ভিজিট করতে পারেন।',

    /*
     * Fallback when product source is not configured.
     */
    'no_source_fallback' => 'Thank you for your message! We will get back to you shortly.',
];
