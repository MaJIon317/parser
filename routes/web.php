<?php

use App\Models\Donor;

Route::get('/test', function () {
    $donor = Donor::first();
dd($donor->toArray());
    return (new \App\Services\Parser\ParserService($donor))->parsePages();
});

/*
 *   "setting" => array:6 [▼
    "language" => "ja"
    "pages" => array:1 [▼
      0 => "https://watchnian.com/shop/r/rbag/?filtercode13=1"
    ]
    "products" => array:1 [▼
      "container" => "//ul[contains(@class,'block-thumbnail-t')]/li[contains(@class,'swiper-slide')]"
    ]
    "product" => array:3 [▼
      "container" => ".//a"
      "has_url" => []
      "price" => array:2 [▼
        "container" => ".//span[contains(@class,'num')]"
        "regular" => null
      ]
    ]
    "pagination" => array:2 [▼
      "container" => "//ul[contains(@class,'pagination')]"
      "has_url" => []
    ]
    "product_page" => array:4 [▼
      "category" => array:2 [▼
        "container" => "(//ul[contains(@class,'block-topic-path--list')]//li[last()]/span)[last()]"
        "regular" => "/【([^】]+)】$/u"
      ]
      "name" => array:2 [▼
        "container" => "//h1[contains(@class,'block-goods-name--text')]"
        "regular" => "/^(.*?)\s*【.*】\s*【.*】$/u"
      ]
      "images" => array:2 [▼
        "container" => "//figure[contains(@class,'block-detail-image-slider--item')]//a[@href]|//figure[contains(@class,'block-detail-image-slider--item')]//img[@data-src]"
        "has_url" => []
      ]
      "attributes" => array:5 [▼
        "container" => "//dl[contains(@class,'goods-spec')]"
        "item" => ".//div[contains(@class,'goods-spec-item')]"
        "key" => ".//dt"
        "value" => ".//dd"
        "nested" => array:4 [▼
          "container" => ".//dl[contains(@class,'goods-spec-disc-list')]"
          "item" => ".//div[contains(@class,'goods-spec-disc-list-item')]"
          "key" => ".//dt"
          "value" => ".//dd"
        ]
      ]
    ]
  ]
 */
