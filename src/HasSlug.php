<?php

namespace Edofre\Sluggable;

use Illuminate\Database\Eloquent\Model;

trait HasSlug
{
    /** @var \Edofre\Sluggable\SlugOptions */
    protected $slugOptions;

    /**
     * Boot the trait.
     */
    protected static function bootHasSlug()
    {
        static::creating(function (Model $model) {
            $model->generateSlugOnCreate();
        });

        static::updating(function (Model $model) {
            $model->generateSlugOnUpdate();
        });
    }

    /**
     * Handle setting slug on explicit request.
     */
    public function generateSlug()
    {
        $this->slugOptions = $this->getSlugOptions();

        $this->addSlug();
    }

    /**
     * Add the slug to the model.
     */
    protected function addSlug()
    {
        $this->guardAgainstInvalidSlugOptions();

        $slug = $this->generateNonUniqueSlug();

        if ($this->slugOptions->generateUniqueSlugs) {
            $slug = $this->makeSlugUnique($slug);
        }

        $slugField = $this->slugOptions->slugField;

        $this->$slugField = $slug;
    }

    /**
     * This function will throw an exception when any of the options is missing or invalid.
     */
    protected function guardAgainstInvalidSlugOptions()
    {
        if (!count($this->slugOptions->generateSlugFrom)) {
            throw InvalidOption::missingFromField();
        }

        if (!strlen($this->slugOptions->slugField)) {
            throw InvalidOption::missingSlugField();
        }

        if ($this->slugOptions->maximumLength <= 0) {
            throw InvalidOption::invalidMaximumLength();
        }
    }

    /**
     * Generate a non unique slug for this record.
     */
    protected function generateNonUniqueSlug()
    {
        if ($this->hasCustomSlugBeenUsed()) {
            $slugField = $this->slugOptions->slugField;

            return $this->$slugField;
        }

        return str_slug($this->getSlugSourceString());
    }

    /**
     * Determine if a custom slug has been saved.
     */
    protected function hasCustomSlugBeenUsed()
    {
        $slugField = $this->slugOptions->slugField;

        return $this->getOriginal($slugField) != $this->$slugField;
    }

    /**
     * Get the string that should be used as base for the slug.
     */
    protected function getSlugSourceString()
    {
        if (is_callable($this->slugOptions->generateSlugFrom)) {
            $slugSourceString = call_user_func($this->slugOptions->generateSlugFrom, $this);

            return substr($slugSourceString, 0, $this->slugOptions->maximumLength);
        }

        $slugSourceString = collect($this->slugOptions->generateSlugFrom)
            ->map(function ($fieldName) {
                return isset($this->$fieldName) ? $this->$fieldName : '';
            })
            ->implode('-');

        return substr($slugSourceString, 0, $this->slugOptions->maximumLength);
    }

    /**
     * Make the given slug unique.
     */
    protected function makeSlugUnique($slug)
    {
        $originalSlug = $slug;
        $i = 1;

        while ($this->otherRecordExistsWithSlug($slug) || $slug === '') {
            $slug = $originalSlug . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Determine if a record exists with the given slug.
     */
    protected function otherRecordExistsWithSlug($slug)
    {
        // Check for SoftDeletes trait
        if (trait_exists('\\App\\Traits\\SoftDeletes') && (in_array(\App\Traits\SoftDeletes::class, class_uses(static::class)))) {
            return static::where($this->slugOptions->slugField, $slug)
                ->withTrashed()
                ->where($this->getKeyName(), '!=', (!is_null($this->getKey()) ? $this->getKey() : '0'))
                ->first();
        } else {
            return (bool)static::where($this->slugOptions->slugField, $slug)
                ->where($this->getKeyName(), '!=', (!is_null($this->getKey()) ? $this->getKey() : '0'))
                ->first();
        }
    }

    /**
     * Get the options for generating the slug.
     */
    abstract public function getSlugOptions();

    /**
     * Handle adding slug on model creation.
     */
    protected function generateSlugOnCreate()
    {
        $this->slugOptions = $this->getSlugOptions();

        if (!$this->slugOptions->generateSlugsOnCreate) {
            return;
        }

        $this->addSlug();
    }

    /**
     * Handle adding slug on model update.
     */
    protected function generateSlugOnUpdate()
    {
        $this->slugOptions = $this->getSlugOptions();

        if (!$this->slugOptions->generateSlugsOnUpdate) {
            return;
        }

        $this->addSlug();
    }
}
