<?php

namespace Cms\Models\Pages;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Templates extends Model
{
    //
    protected $table = 'web_page_templates';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'title',
        'menu_title',
        'footer_title',
        'footer_order',
        'seo_keyword_id',
        'meta_title',
        'meta_description',
        'layout_id',
        'url',
        'content',
        'view',
        'parent',
        'category',
        'page_order',
        'header_position',
        'footer_position',
        'status',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function blocks(){
        return $this->hasMany('Cms\Models\Pages\Blocks', 'template_id', 'id');
    }

    public function layouts(){
        return $this->hasOne('Cms\Models\Pages\Layouts', 'id', 'layout_id');
    }

    /**
     * Whether this page is assembled from builder components rather than a
     * developer-written Blade view.
     */
    public function hasBuilderContent(): bool
    {
        return filled($this->content);
    }

    /**
     * The published Blade view drawing this page's content, if it has one and
     * the file is there. When it is, the page's components are not shown.
     */
    public function publishedView(): ?string
    {
        return filled($this->view) && view()->exists($this->view) ? $this->view : null;
    }

    
    // with the block method, query the groups table
    public function groups(){
        return $this->hasManyThrough('Cms\Models\Pages\Groups', 'Cms\Models\Pages\Blocks', 'template_id', 'block_id', 'id', 'id');
    }

    // meta keyword
    public function metakeywords(){
        return $this->hasOne('Cms\Models\Pages\SeoKeyword', 'id', 'seo_keyword_id');
    }

    /**
     * The page this one is nested under, if any.
     */
    public function parentPage()
    {
        return $this->belongsTo(self::class, 'parent', 'id');
    }

    /**
     * Pages nested directly under this one in the menus.
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent', 'id')->orderBy('page_order');
    }

    /**
     * Whether this page heads a group in the menus.
     */
    public function isMenuGroup(): bool
    {
        return $this->children()->exists();
    }

    /**
     * The public URL for this page.
     *
     * The home page is stored with the slug "home" but is served from the
     * site root, so it is the one page whose link is not its slug.
     */
    public function link(): string
    {
        return $this->url === 'home' ? url('/') : url('/' . ltrim($this->url, '/'));
    }

    /**
     * The path this page is served from, for disambiguating menu options —
     * "Company Overview" is a lot clearer alongside "/about".
     */
    public function getLinkPathAttribute(): string
    {
        return $this->url === 'home' ? '/' : '/' . ltrim((string) $this->url, '/');
    }

    /**
     * How this page is labelled in a menu.
     *
     * Falls back to the page's own title, so a menu label only has to be set
     * where the two genuinely differ.
     */
    public function menuLabel(): string
    {
        return $this->menu_title ?: $this->title;
    }

    /**
     * How this page is labelled in the footer, which occasionally words a
     * link differently from the header.
     */
    public function footerLabel(): string
    {
        return $this->footer_title ?: $this->menuLabel();
    }

    /**
     * Whether a dropdown should list this page itself before its children.
     *
     * A group with its own menu wording — "About Us" heading a page titled
     * "Company Overview" — needs the overview page listed inside the dropdown
     * too, or it becomes unreachable from the menu. A group whose label is
     * just its title does not, because the heading already links to it.
     */
    public function listsItselfInMenu(): bool
    {
        return filled($this->menu_title) && $this->menu_title !== $this->title;
    }

    protected static function booted()
    {
        static::creating(function ($seo) {
            $seo->uuid = Str::uuid()->toString();
        });
    }
}
