<?php
// Step 5: Review/Confirm - Show all data in tabular form for confirmation
?>
<h5 class="mb-3">Review Changes Before Saving</h5>
<div class="alert alert-info">Please review the entered/changed information before submitting. Use Back to make corrections.</div>
<table class="table table-sm table-striped">
    <tbody>
        <?php foreach ($_POST as $field => $value): ?>
            <?php
            if (is_array($value)) continue; // skip arrays (shown below)
            ?>
            <tr>
                <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?></th>
                <td><?= htmlspecialchars($value) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
// Show array fields (children, academic, etc.)
function render_array_field($label, $arr, $keys) {
    if (!$arr || !is_array($arr) || count(array_filter($arr[0])) === 0) return;
    echo "<h6 class=\"mt-3\">$label</h6><table class=\"table table-sm table-bordered\"><thead><tr>";
    foreach ($keys as $k) echo "<th>" . htmlspecialchars(ucwords(str_replace('_', ' ', $k))) . "</th>";
    echo "</tr></thead><tbody>";
    for ($i = 0; $i < count($arr[$keys[0]]); $i++) {
        echo "<tr>";
        foreach ($keys as $k) echo "<td>" . htmlspecialchars($arr[$k][$i] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}
render_array_field('Children', $_POST['child_name'] ? ['child_name'=>$_POST['child_name'],'child_dob'=>$_POST['child_dob'],'child_nrc'=>$_POST['child_nrc'],'child_relationship'=>$_POST['child_relationship'],'child_gender'=>$_POST['child_gender']] : [], ['child_name','child_dob','child_nrc','child_relationship','child_gender']);
render_array_field('Academic Qualifications', $_POST['academic_institution'] ? ['academic_institution'=>$_POST['academic_institution'],'academic_start'=>$_POST['academic_start'],'academic_end'=>$_POST['academic_end'],'academic_qualification'=>$_POST['academic_qualification']] : [], ['academic_institution','academic_start','academic_end','academic_qualification']);
render_array_field('Professional/Technical', $_POST['proftech_profession'] ? ['proftech_profession'=>$_POST['proftech_profession'],'proftech_course'=>$_POST['proftech_course'],'proftech_letters'=>$_POST['proftech_letters'],'proftech_institution'=>$_POST['proftech_institution'],'proftech_year'=>$_POST['proftech_year']] : [], ['proftech_profession','proftech_course','proftech_letters','proftech_institution','proftech_year']);
render_array_field('Military Courses', $_POST['milcourse_name'] ? ['milcourse_name'=>$_POST['milcourse_name'],'milcourse_institution'=>$_POST['milcourse_institution'],'milcourse_start'=>$_POST['milcourse_start'],'milcourse_end'=>$_POST['milcourse_end'],'milcourse_result'=>$_POST['milcourse_result'],'milcourse_type'=>$_POST['milcourse_type']] : [], ['milcourse_name','milcourse_institution','milcourse_start','milcourse_end','milcourse_result','milcourse_type']);
render_array_field('Trade Groups', $_POST['tradegroup_employment'] ? ['tradegroup_employment'=>$_POST['tradegroup_employment'],'tradegroup_group'=>$_POST['tradegroup_group'],'tradegroup_class'=>$_POST['tradegroup_class'],'tradegroup_date'=>$_POST['tradegroup_date'],'tradegroup_authority'=>$_POST['tradegroup_authority']] : [], ['tradegroup_employment','tradegroup_group','tradegroup_class','tradegroup_date','tradegroup_authority']);
render_array_field('Awards', $_POST['award_name'] ? ['award_name'=>$_POST['award_name'],'award_date'=>$_POST['award_date'],'award_authority'=>$_POST['award_authority']] : [], ['award_name','award_date','award_authority']);
render_array_field('Appointments', $_POST['appointment_name'] ? ['appointment_name'=>$_POST['appointment_name'],'appointment_unit'=>$_POST['appointment_unit'],'appointment_start'=>$_POST['appointment_start'],'appointment_end'=>$_POST['appointment_end'],'appointment_authority'=>$_POST['appointment_authority']] : [], ['appointment_name','appointment_unit','appointment_start','appointment_end','appointment_authority']);
render_array_field('Promotions', $_POST['promotion_rank'] ? ['promotion_rank'=>$_POST['promotion_rank'],'promotion_date_from'=>$_POST['promotion_date_from'],'promotion_date_to'=>$_POST['promotion_date_to'],'promotion_next_rank'=>$_POST['promotion_next_rank'],'promotion_type'=>$_POST['promotion_type'],'promotion_authority'=>$_POST['promotion_authority'],'promotion_remark'=>$_POST['promotion_remark']] : [], ['promotion_rank','promotion_date_from','promotion_date_to','promotion_next_rank','promotion_type','promotion_authority','promotion_remark']);
?>