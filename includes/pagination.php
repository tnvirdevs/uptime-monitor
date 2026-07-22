<?php if (($paginator ?? null) instanceof Paginator && $paginator->pages() > 1): ?>
    <nav aria-label="Pagination">
        <ul class="pagination pagination-sm mb-0">
            <?php for ($i = 1; $i <= $paginator->pages(); $i++): ?>
                <?php $params = array_merge($_GET, ['page' => $i]); ?>
                <li class="page-item <?= $paginator->page === $i ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= e(http_build_query($params)) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>
