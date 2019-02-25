<?php


namespace CommonBundle\DataFixtures;


use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use VoucherBundle\Entity\Product;


class ProductFixtures extends Fixture
{

    private $data = [
        ['soap', 'Unit', '../../assets/imgs/soap.jpg', 0],
        ['toothbrush', 'Unit', '../../assets/imgs/toothbrush.jpg', 0],
        ['pear', 'KG', '../../assets/imgs/pear.jpg', 0],
        ['rice', 'KG', '../../assets/imgs/rice.jpg', 0],
        ['flour', 'KG', '../../assets/imgs/flour.jpg', 0],
        ['toothpaste', 'Unit', '../../assets/imgs/toothpaste.jpg', 0],
        ['apple', 'KG', '../../assets/imgs/apples.jpeg', 0],
        ['hair brush', 'Unit', '../../assets/imgs/hairbrush.jpg', 0],
        ['cherry', 'KG', '../../assets/imgs/cherries.jpg', 0],
        ['book', 'Unit', '../../assets/imgs/book.png', 0],
        ['cake', 'Unit', '../../assets/imgs/cake.jpg', 0],

    ];

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        foreach ($this->data as $datum)
        {
            $product = new Product();
            $product->setName($datum[0])
                ->setUnit($datum[1])
                ->setImage($datum[2])
                ->setArchived($datum[3]);
            $manager->persist($product);
            $manager->flush();
        }
    }
}