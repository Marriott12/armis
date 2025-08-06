<div class="tab-pane fade p-3 border rounded <?=isset($tabErrors['language']) ? 'show active' : ''?>" id="language" role="tabpanel">
    <h5 class="mb-3 text-success">Language Proficiency</h5>
    <div id="languageList"></div>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addLanguage()">Add Language</button>
</div>