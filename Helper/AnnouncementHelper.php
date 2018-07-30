<?php

/*
 * This file is part of the Zikula package.
 *
 * Copyright Zikula Foundation - https://ziku.la/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zikula\Module\CoreManagerModule\Helper;

use Doctrine\ORM\EntityManagerInterface;
use MU\NewsModule\Entity\Factory\EntityFactory;
use MU\NewsModule\Entity\MessageEntity;
use MU\NewsModule\Entity\MessageCategoryEntity;
use MU\NewsModule\Helper\TranslatableHelper;
use MU\NewsModule\Helper\WorkflowHelper;
use Zikula\Bundle\CoreBundle\HttpKernel\ZikulaHttpKernelInterface;
use Zikula\CategoriesModule\Entity\RepositoryInterface\CategoryRepositoryInterface;
use Zikula\Common\Translator\TranslatorInterface;
use Zikula\Common\Translator\TranslatorTrait;
use Zikula\Module\CoreManagerModule\Entity\CoreReleaseEntity;

class AnnouncementHelper
{
    use TranslatorTrait;

    const NEWS_DESCRIPTION_START = "<!-- %START_AUTOGENERATED% Do not touch the content below! If you want to change it, edit the release at GitHub! -->";

    const NEWS_DESCRIPTION_END = "<!-- You can edit content below this line %END_AUTOGENERATED% -->";

    /**
     * @var ZikulaHttpKernelInterface
     */
    private $kernel;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var EntityFactory
     */
    private $entityFactory = null;

    /**
     * @var WorkflowHelper
     */
    private $workflowHelper = null;

    /**
     * @var TranslatableHelper
     */
    private $translatableHelper = null;

    /**
     * @var CoreReleaseEntity
     */
    private $release = null;

    /**
     * @var array
     */
    private $translationLocales = [];

    /**
     * @param TranslatorInterface $translator
     * @param ZikulaHttpKernelInterface $kernel
     * @param EntityManagerInterface $em
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        TranslatorInterface $translator,
        ZikulaHttpKernelInterface $kernel,
        EntityManagerInterface $em,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->setTranslator($translator);
        $this->kernel = $kernel;
        $this->em = $em;
        $this->categoryRepository = $categoryRepository;
        $this->translationLocales = ['de'];
    }

    public function setTranslator($translator)
    {
        $this->translator = $translator;
    }

    /**
     * Sets News entity factory reference.
     *
     * @param EntityFactory $entityFactory
     */
    public function setNewsEntityFactory(EntityFactory $entityFactory)
    {
        $this->entityFactory = $entityFactory;
    }

    /**
     * Sets News workflow helper reference.
     *
     * @param WorkflowHelper $workflowHelper
     */
    public function setNewsWorkflowHelper(WorkflowHelper $workflowHelper)
    {
        $this->workflowHelper = $workflowHelper;
    }

    /**
     * Sets News translatable helper reference.
     *
     * @param TranslatableHelper $translatableHelper
     */
    public function setNewsTranslatableHelper(TranslatableHelper $translatableHelper)
    {
        $this->translatableHelper = $translatableHelper;
    }

    /**
     * Creates a news article about a new release.
     *
     * @param CoreReleaseEntity $release
     */
    public function createNewsArticle(CoreReleaseEntity $release)
    {
        if (!$this->kernel->isBundle('MUNewsModule')) {
            return;
        }
        $this->release = $release;

        $releaseName = $release->getNameI18n();

        $title = $teaser = '';
        switch ($release->getState()) {
            case CoreReleaseEntity::STATE_SUPPORTED:
                $title = $this->__f('%s released!', ['%s' => $releaseName]);
                $teaser = '<p>' . $this->__f('The core development team is proud to announce the availabilty of %s.', ['%s' => $releaseName]) . '</p>';
                break;
            case CoreReleaseEntity::STATE_PRERELEASE:
                $title = $this->__f('%s ready for testing!', ['%s' => $releaseName]);
                $teaser = '<p>' . $this->__f('The core development team is proud to announce a pre-release of %s. Please help testing and report bugs!', ['%s' => $releaseName]) . '</p>';
                break;
            case CoreReleaseEntity::STATE_DEVELOPMENT:
            case CoreReleaseEntity::STATE_OUTDATED:
            default:
                // Do not create news post.
                return;
        }

        $article = $this->entityFactory->createMessage();
        $article->setTitle($title);
        $article->setStartText($teaser);
        $this->updateNewsText($article);
        $article->setAuthor('Admin');

        $registryId = 3;
        $category = $this->categoryRepository->find(10001); // Release
        $categoryAssignment = new MessageCategoryEntity($registryId, $category, $article);
        $article->getCategories()->add($categoryAssignment);
        $this->em->persist($categoryAssignment);

        // for testing:
        //$this->workflowHelper->executeAction($article, 'submit');
        // for production:
        $this->workflowHelper->executeAction($article, 'approve');

        $id = $article->getId();

        foreach ($this->translationLocales as $locale) {
            $releaseName = $release->getNameI18n($locale);
            if ($locale == 'de') {
                if (CoreReleaseEntity::STATE_SUPPORTED == $release->getState()) {
                    $title = $releaseName . ' veröffentlicht!';
                    $teaser = '<p>Das Core-Team freut sich mitzuteilen, dass ' . $releaseName . ' nun verfügbar ist.</p>';
                } elseif (CoreReleaseEntity::STATE_PRERELEASE == $release->getState()) {
                    $title = $releaseName . ' steht zum Testen bereit!';
                    $teaser = '<p>Das Core-Team freut sich mitzuteilen, dass eine Vorabversion von ' . $releaseName . ' nun verfügbar ist. Bitte helft beim Testen und meldet etwaige Fehler!</p>';
                }
            }

            $article->setTitle($title);
            $article->setStartText($teaser);
            $this->updateNewsText($article, $locale);
            $this->em->flush();
        }

        if (is_numeric($id) && $id > 0) {
            $release->setNewsId($id);
            $this->em->merge($release);
            $this->em->flush();
        }
    }

    /**
     * Updates download links of a news article.
     *
     * @param CoreReleaseEntity $release
     */
    public function updateNewsArticle(CoreReleaseEntity $release)
    {
        if (null === $release->getNewsId() || !$this->kernel->isBundle('MUNewsModule')) {
            return;
        }
        $this->release = $release;

        $article = $this->entityFactory->getRepository('message')->selectById($release->getNewsId());
        if (!$article) {
            return;
        }

        $this->updateNewsText($article);

        // for testing:
        //$this->workflowHelper->executeAction($article, 'updatewaiting');
        // for production:
        $this->workflowHelper->executeAction($article, 'updateapproved');

        $translations = $this->translatableHelper->prepareEntityForEditing($article);
        foreach ($this->translationLocales as $locale) {
            if (!isset($translations[$locale])) {
                continue;
            }
            // apply existing translation data
            foreach ($translations[$locale] as $fieldName => $value) {
                $article[$fieldName] = $value;
            }

            $this->updateNewsText($article, $locale);

            $this->em->flush();
        }
    }

    /**
     * Returns a news text to use for a given core release.
     *
     * @param string $locale
     * @return string
     */
    private function getNewsText($locale = '')
    {
        $downloadLinks = '';
        if (count($this->release->getAssets()) > 0) {
            $downloadLinkTpl = '<a href="%link%" class="btn btn-success btn-sm">%text%</a>';
            foreach ($this->release->getAssets() as $asset) {
                $downloadLinks .= str_replace('%link%', $asset['download_url'], str_replace('%text%', $asset['name'], $downloadLinkTpl));
            }
        } else {
            $downloadLinks .= '<p class="alert alert-warning">' .
                $this->__('Direct download links not yet available!') . '</p>';
        }

        return self::NEWS_DESCRIPTION_START .
            $this->release->getDescriptionI18n($locale) . $downloadLinks .
            self::NEWS_DESCRIPTION_END;
    }

    /**
     * Updates the news text for a given article.
     *
     * @param MessageEntity $article
     * @param string $locale
     */
    private function updateNewsText(MessageEntity $article, $locale = '')
    {
        $body = $this->getNewsText($locale);
        if ($article->getMainText() != '') {
            $body = preg_replace(
                '#' . preg_quote(self::NEWS_DESCRIPTION_START) . '.*?' . preg_quote(self::NEWS_DESCRIPTION_END) . '#',
                $body,
                $article->getMainText()
            );
        }

        $article->setMainText($body);

        if ($locale != '') {
            $article->setLocale($locale);
        }
    }
}
