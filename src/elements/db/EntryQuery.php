<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\app\elements\db;

use Craft;
use craft\app\db\Query;
use craft\app\db\QueryAbortedException;
use craft\app\elements\Entry;
use craft\app\helpers\ArrayHelper;
use craft\app\helpers\Db;
use craft\app\models\EntryType;
use craft\app\models\Section;
use craft\app\models\UserGroup;
use DateTime;

/**
 * EntryQuery represents a SELECT SQL statement for entries in a way that is independent of DBMS.
 *
 * @property DateTime|string           $before      The date/time that the resulting entries’ Post Dates must be before.
 * @property DateTime|string           $after       The date/time that the resulting entries’ Post Dates must be equal to or after.
 * @property string|string[]|Section   $section     The handle(s) of the section(s) that resulting entries must belong to.
 * @property string|string[]|EntryType $type        The handle(s) of the entry type(s) that resulting entries must have.
 * @property string|string[]|UserGroup $authorGroup The handle(s) of the user group(s) that resulting entries’ authors must belong to.
 *
 * @method Entry[]|array all($db = null)
 * @method Entry|array|null one($db = null)
 * @method Entry|array|null nth($n, $db = null)
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class EntryQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    // General parameters
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public $status = 'live';

    /**
     * @var boolean Whether to only return entries that the user has permission to edit.
     */
    public $editable;

    /**
     * @var integer|integer[] The section ID(s) that the resulting entries must be in.
     */
    public $sectionId;

    /**
     * @var integer|integer[] The entry type ID(s) that the resulting entries must have.
     */
    public $typeId;

    /**
     * @var integer|integer[] The user ID(s) that the resulting entries’ authors must have.
     */
    public $authorId;

    /**
     * @var integer|integer[] The user group ID(s) that the resulting entries’ authors must be in.
     */
    public $authorGroupId;

    /**
     * @var mixed The Post Date that the resulting entries must have.
     */
    public $postDate;

    /**
     * @var mixed The Expiry Date that the resulting entries must have.
     */
    public $expiryDate;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'section': {
                $this->section($value);
                break;
            }
            case 'type': {
                $this->type($value);
                break;
            }
            case 'authorGroup': {
                $this->authorGroup($value);
                break;
            }
            case 'before': {
                $this->before($value);
                break;
            }
            case 'after': {
                $this->after($value);
                break;
            }
            default: {
                parent::__set($name, $value);
            }
        }
    }

    /**
     * Sets the [[editable]] property.
     *
     * @param boolean $value The property value (defaults to true)
     *
     * @return $this self reference
     */
    public function editable($value = true)
    {
        $this->editable = $value;

        return $this;
    }

    /**
     * Sets the [[sectionId]] property based on a given section(s)’s handle(s).
     *
     * @param string|string[]|Section $value The property value
     *
     * @return $this self reference
     */
    public function section($value)
    {
        if ($value instanceof Section) {
            $this->structureId = ($value->structureId ?: false);
            $this->sectionId = $value->id;
        } else {
            $query = new Query();
            $this->sectionId = $query
                ->select(['id'])
                ->from('{{%sections}}')
                ->where(Db::parseParam('handle', $value))
                ->column();
        }

        return $this;
    }

    /**
     * Sets the [[sectionId]] property.
     *
     * @param integer|integer[] $value The property value
     *
     * @return $this self reference
     */
    public function sectionId($value)
    {
        $this->sectionId = $value;

        return $this;
    }

    /**
     * Sets the [[typeId]] property based on a given entry type(s)’s handle(s).
     *
     * @param string|string[]|EntryType $value The property value
     *
     * @return $this self reference
     */
    public function type($value)
    {
        if ($value instanceof EntryType) {
            $this->typeId = $value->id;
        } else {
            $query = new Query();
            $this->typeId = $query
                ->select(['id'])
                ->from('{{%entrytypes}}')
                ->where(Db::parseParam('handle', $value))
                ->column();
        }

        return $this;
    }

    /**
     * Sets the [[typeId]] property.
     *
     * @param integer|integer[] $value The property value
     *
     * @return $this self reference
     */
    public function typeId($value)
    {
        $this->typeId = $value;

        return $this;
    }

    /**
     * Sets the [[authorId]] property.
     *
     * @param integer|integer[] $value The property value
     *
     * @return $this self reference
     */
    public function authorId($value)
    {
        $this->authorId = $value;

        return $this;
    }

    /**
     * Sets the [[authorGroupId]] property based on a given user group(s)’s handle(s).
     *
     * @param string|string[]| $value The property value
     *
     * @return $this self reference
     */
    public function authorGroup($value)
    {
        if ($value instanceof UserGroup) {
            $this->authorGroupId = $value->id;
        } else {
            $query = new Query();
            $this->authorGroupId = $query
                ->select(['id'])
                ->from('{{%usergroups}}')
                ->where(Db::parseParam('handle', $value))
                ->column();
        }

        return $this;
    }

    /**
     * Sets the [[authorGroupId]] property.
     *
     * @param integer|integer[] $value The property value
     *
     * @return $this self reference
     */
    public function authorGroupId($value)
    {
        $this->authorGroupId = $value;

        return $this;
    }

    /**
     * Sets the [[postDate]] property.
     *
     * @param mixed $value The property value
     *
     * @return $this self reference
     */
    public function postDate($value)
    {
        $this->postDate = $value;

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow entries whose Post Date is before the given value.
     *
     * @param DateTime|string $value The property value
     *
     * @return $this self reference
     */
    public function before($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '<'.$value;

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow entries whose Post Date is after the given value.
     *
     * @param DateTime|string $value The property value
     *
     * @return $this self reference
     */
    public function after($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '>='.$value;

        return $this;
    }

    /**
     * Sets the [[expiryDate]] property.
     *
     * @param mixed $value The property value
     *
     * @return $this self reference
     */
    public function expiryDate($value)
    {
        $this->expiryDate = $value;

        return $this;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare()
    {
        // See if 'section', 'type', or 'authorGroup' were set to invalid handles
        if ($this->sectionId === [] || $this->typeId === [] || $this->authorGroupId === []) {
            return false;
        }

        $this->joinElementTable('entries');

        $this->query->select([
            'entries.sectionId',
            'entries.typeId',
            'entries.authorId',
            'entries.postDate',
            'entries.expiryDate',
        ]);

        if ($this->postDate) {
            $this->subQuery->andWhere(Db::parseDateParam('entries.postDate', $this->postDate));
        }

        if ($this->expiryDate) {
            $this->subQuery->andWhere(Db::parseDateParam('entries.expiryDate', $this->expiryDate));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('entries.typeId', $this->typeId));
        }

        if (Craft::$app->getEdition() >= Craft::Client) {
            if ($this->authorId) {
                $this->subQuery->andWhere(Db::parseParam('entries.authorId', $this->authorId));
            }

            if ($this->authorGroupId) {
                $this->subQuery
                    ->innerJoin('{{%usergroups_users}} usergroups_users', '[[usergroups_users.userId]] = [[entries.authorId]]')
                    ->andWhere(Db::parseParam('usergroups_users.groupId', $this->authorGroupId));
            }
        }

        $this->_applyEditableParam();
        $this->_applySectionIdParam();
        $this->_applyRefParam();

        if ($this->orderBy === null && !$this->structureId) {
            $this->orderBy = 'postDate desc';
        }

        return parent::beforePrepare();
    }

    // Private Methods
    // =========================================================================

    /**
     * Applies the 'editable' param to the query being prepared.
     *
     * @return void
     * @throws QueryAbortedException
     */
    private function _applyEditableParam()
    {
        if (!$this->editable) {
            return;
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user) {
            throw new QueryAbortedException();
        }

        // Limit the query to only the sections the user has permission to edit
        $this->subQuery->andWhere([
            'entries.sectionId' => Craft::$app->getSections()->getEditableSectionIds()
        ]);

        // Enforce the editPeerEntries permissions for non-Single sections
        foreach (Craft::$app->getSections()->getEditableSections() as $section) {
            if ($section->type != Section::TYPE_SINGLE && !$user->can('editPeerEntries:'.$section->id)) {
                $this->subQuery->andWhere([
                    'or',
                    ['not', ['entries.sectionId' => $section->id]],
                    ['entries.authorId' => $user->id]
                ]);
            }
        }
    }

    /**
     * Applies the 'sectionId' param to the query being prepared.
     */
    private function _applySectionIdParam()
    {
        if ($this->sectionId) {
            // Should we set the structureId param?
            if ($this->structureId === null && (!is_array($this->sectionId) || count($this->sectionId) === 1)) {
                $query = new Query();
                $this->structureId = $query
                    ->select(['structureId'])
                    ->from('{{%sections}}')
                    ->where(Db::parseParam('id', $this->sectionId))
                    ->scalar();
            }

            $this->subQuery->andWhere(Db::parseParam('entries.sectionId', $this->sectionId));
        }
    }

    /**
     * Applies the 'ref' param to the query being prepared.
     *
     * @return void
     */
    private function _applyRefParam()
    {
        if (!$this->ref) {
            return;
        }

        $refs = ArrayHelper::toArray($this->ref);
        $joinSections = false;
        $condition = ['or'];

        foreach ($refs as $ref) {
            $parts = array_filter(explode('/', $ref));

            if ($parts) {
                if (count($parts) == 1) {
                    $condition[] = Db::parseParam('elements_i18n.slug', $parts[0]);
                } else {
                    $condition[] = [
                        'and',
                        Db::parseParam('sections.handle', $parts[0]),
                        Db::parseParam('elements_i18n.slug', $parts[1])
                    ];
                    $joinSections = true;
                }
            }
        }

        $this->subQuery->andWhere($condition);

        if ($joinSections) {
            $this->subQuery->innerJoin('{{%sections}} sections', '[[sections.id]] = [[entries.sectionId]]');
        }
    }
}
