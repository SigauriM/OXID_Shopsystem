#!/usr/bin/env php
<?php

declare(strict_types=1);

use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\Category;
use OxidEsales\Eshop\Application\Model\Object2Category;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed-catalog.php can only run from the CLI.\n");
    exit(1);
}

$bootstrap = dirname(__DIR__) . '/source/bootstrap.php';
if (!is_file($bootstrap)) {
    fwrite(STDERR, "Shop bootstrap is missing. Run bin/dev-setup.sh first.\n");
    exit(1);
}

require $bootstrap;
restore_exception_handler();
Registry::getConfig();

const CATEGORY_TITLE = 'Gerüst und Leitern';
const ARTICLE_PRICE = 99.00;
const ARTICLE_VAT = 19;
const ARTICLE_STOCK = 100;
const ARTICLE_STOCK_FLAG = 1;

/**
 * Stage 2 catalog: packed length/width/height in metres, weight in kilograms.
 *
 * @var list<array{0: string, 1: string, 2: float, 3: float, 4: float, 5: float}>
 */
const ARTICLES = [
    ['LAD-200', 'Anlegeleiter 2,0 m', 2.00, 0.34, 0.08, 5.6],
    ['LAD-250', 'Anlegeleiter 2,5 m', 2.50, 0.35, 0.08, 6.8],
    ['LAD-300', 'Anlegeleiter 3,0 m', 3.00, 0.38, 0.09, 8.5],
    ['LAD-400', 'Anlegeleiter 4,0 m', 4.00, 0.42, 0.10, 11.5],
    ['LAD-500', 'Anlegeleiter 5,0 m', 5.00, 0.44, 0.11, 14.2],
    ['LAD-600', 'Anlegeleiter 6,0 m', 6.00, 0.45, 0.12, 17.8],
    ['GER-RAHMEN', 'Gerüstrahmen 2,0×0,7 m', 2.00, 0.70, 0.05, 11.0],
    ['GER-BUEHNE', 'Gerüstbühne 2,5×0,60 m', 2.50, 0.60, 0.10, 9.5],
    ['KAR-SACK', 'Sackkarre', 1.30, 0.55, 0.48, 12.5],
    ['KAR-PLATT', 'Plattformwagen', 1.20, 0.80, 0.35, 18.0],
];

function existingOxid(string $sql, array $params): ?string
{
    $id = DatabaseProvider::getDb()->getOne($sql, $params);
    if (!is_string($id) && !is_numeric($id)) {
        return null;
    }

    $id = (string) $id;

    return $id === '' ? null : $id;
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$categoryId = existingOxid(
    'SELECT OXID FROM oxcategories WHERE OXTITLE = :title',
    [':title' => CATEGORY_TITLE],
);

if ($categoryId === null) {
    $category = oxNew(Category::class);
    $category->setId(Registry::getUtilsObject()->generateUId());
    $category->oxcategories__oxparentid = new Field('oxrootid');
    $category->oxcategories__oxtitle = new Field(CATEGORY_TITLE);
    $category->oxcategories__oxactive = new Field(1);
    $category->oxcategories__oxhidden = new Field(0);
    if ($category->save() === false) {
        fail('Failed to save category "' . CATEGORY_TITLE . '".');
    }
    $categoryId = $category->getId();
    if (!is_string($categoryId) || $categoryId === '') {
        fail('Category "' . CATEGORY_TITLE . '" saved without an OXID.');
    }
    fwrite(STDOUT, 'Created category: ' . CATEGORY_TITLE . "\n");
} else {
    fwrite(STDOUT, 'Category already exists, skipping: ' . CATEGORY_TITLE . "\n");
}

foreach (ARTICLES as $row) {
    [$articleNumber, $title, $length, $width, $height, $weight] = $row;

    $existingId = existingOxid(
        'SELECT OXID FROM oxarticles WHERE OXARTNUM = :artnum',
        [':artnum' => $articleNumber],
    );
    if ($existingId !== null) {
        fwrite(STDOUT, 'Article already exists, skipping: ' . $articleNumber . "\n");
        continue;
    }

    $article = oxNew(Article::class);
    $article->setId(Registry::getUtilsObject()->generateUId());
    $article->oxarticles__oxartnum = new Field($articleNumber);
    $article->oxarticles__oxtitle = new Field($title);
    $article->oxarticles__oxactive = new Field(1);
    $article->oxarticles__oxprice = new Field(ARTICLE_PRICE);
    $article->oxarticles__oxvat = new Field(ARTICLE_VAT);
    $article->oxarticles__oxstock = new Field(ARTICLE_STOCK);
    $article->oxarticles__oxstockflag = new Field(ARTICLE_STOCK_FLAG);
    $article->oxarticles__oxlength = new Field($length);
    $article->oxarticles__oxwidth = new Field($width);
    $article->oxarticles__oxheight = new Field($height);
    $article->oxarticles__oxweight = new Field($weight);
    if ($article->save() === false) {
        fail('Failed to save article ' . $articleNumber . '.');
    }

    $assignment = oxNew(Object2Category::class);
    $assignment->setProductId($article->getId());
    $assignment->setCategoryId($categoryId);
    $assignment->oxobject2category__oxtime = new Field(time());
    if ($assignment->save() === false) {
        fail('Failed to assign article ' . $articleNumber . ' to the category.');
    }

    fwrite(STDOUT, 'Created article: ' . $articleNumber . "\n");
}

fwrite(STDOUT, "Catalog seed finished.\n");
