<?php
// filepath: users/admin_branch/partials/create_staff_handle_post.php

require_once '../../../auth.php';

$errors = [];
$tabErrors = [];
$success = false;

// Use global sanitize helpers if available, otherwise define them
if (!function_exists('sanitize')) {
    function sanitize($value) {
        return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('sanitize_array')) {
    function sanitize_array($arr) {
        return array_map('sanitize', $arr ?? []);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check using our native system
    if (!isset($_POST['csrf_token']) || !CSRFToken::validate($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // Required fields (Personal/Service)
        $required = [
            'svcNo','lname','fname','nrc_part1','nrc_part2','gender','DOB','svcStatus','marital','dateOfEnlistment','nok','nok_nrc','nok_relationship','nok_tel','category','prefix','height','combat_size','boot_size','shoe_size','headdress_size','blood_group','province'
        ];
        foreach ($required as $field) {
            if (empty(sanitize($_POST[$field] ?? ''))) {
                $errors[] = "Field '$field' is required.";
                $tabErrors['personal'] = true;
            }
        }

        // Residence (Static)
        $residence_required = ['province', 'district', 'township', 'village', 'plot_no'];
        foreach ($residence_required as $field) {
            if (empty(sanitize($_POST[$field] ?? ''))) {
                $errors[] = "Residence field '$field' is required.";
                $tabErrors['residence'] = true;
            }
        }

        // --- INTELLIGENT RANK LOGIC ---
        $selectedRankID = sanitize($_POST['rankID'] ?? null);
        $selectedRankName = '';
        if (!empty($selectedRankID) && isset($ranks) && is_array($ranks)) {
            foreach ($ranks as $rank) {
                if (is_object($rank) && isset($rank->rankID) && $rank->rankID == $selectedRankID) {
                    $selectedRankName = $rank->rankName;
                    break;
                }
            }
        }
        if (stripos($selectedRankName, 'Temporal') !== false) {
            $tempRank = $selectedRankID;
            $tempWef = sanitize($_POST['dateOfEnlistment'] ?? null);
            $subRank = null;
            $subWef = null;
        } else {
            $tempRank = null;
            $tempWef = null;
            $subRank = $selectedRankID;
            $subWef = sanitize($_POST['dateOfEnlistment'] ?? null);
        }

        // If no errors, insert into staff table
        if (empty($errors)) {
            $data = [
                'svcNo'        => sanitize($_POST['svcNo']),
                'rankID'       => $selectedRankID,
                'lname'        => sanitize($_POST['lname']),
                'fname'        => sanitize($_POST['fname']),
                'NRC'          => sanitize($_POST['nrc_part1']) . '/' . sanitize($_POST['nrc_part2']) . '/1',
                'passport'     => sanitize($_POST['passport'] ?? ''),
                'passExp'      => sanitize($_POST['passport_exp_date'] ?? ''),
                'combatSize'   => sanitize($_POST['combat_size'] ?? ''),
                'bsize'        => sanitize($_POST['boot_size'] ?? ''),
                'ssize'        => sanitize($_POST['shoe_size'] ?? ''),
                'hdress'       => sanitize($_POST['headdress_size'] ?? ''),
                'gender'       => sanitize($_POST['gender'] ?? ''),
                'unitID'       => sanitize($_POST['unitID'] ?? ''),
                'category'     => sanitize($_POST['category'] ?? ''),
                'svcStatus'    => sanitize($_POST['svcStatus'] ?? ''),
                'appt'         => sanitize($_POST['appt'] ?? ''),
                'subRank'      => $subRank,
                'subWef'       => $subWef,
                'tempRank'     => $tempRank,
                'tempWef'      => $tempWef,
                'localRank'    => sanitize($_POST['localRank'] ?? ''),
                'localWef'     => sanitize($_POST['localWef'] ?? ''),
                'attestDate'   => sanitize($_POST['dateOfEnlistment'] ?? ''),
                'intake'       => sanitize($_POST['intake'] ?? ''),
                'DOB'          => sanitize($_POST['DOB'] ?? ''),
                'height'       => sanitize($_POST['height'] ?? ''),
                'province'     => sanitize($_POST['province'] ?? ''),
                'corps'        => sanitize($_POST['corps'] ?? ''),
                'bloodGp'      => sanitize($_POST['blood_group'] ?? ''),
                'profession'   => sanitize($_POST['profession'] ?? ''),
                'trade'        => sanitize($_POST['trade'] ?? ''),
                'digitalID'    => sanitize($_POST['digitalID'] ?? ''),
                'prefix'       => sanitize($_POST['prefix'] ?? ''),
                'marital'      => sanitize($_POST['marital'] ?? ''),
                'initials'     => sanitize($_POST['initials'] ?? ''),
                'titles'       => sanitize($_POST['titles'] ?? ''),
                'nok'          => sanitize($_POST['nok'] ?? ''),
                'nokNrc'       => sanitize($_POST['nok_nrc'] ?? ''),
                'nokRelat'     => sanitize($_POST['nok_relationship'] ?? ''),
                'nokTel'       => sanitize($_POST['nok_tel'] ?? ''),
                'altNok'       => sanitize($_POST['altNok'] ?? ''),
                'altNokTel'    => sanitize($_POST['altNokTel'] ?? ''),
                'altNokNRC'    => sanitize($_POST['altNokNRC'] ?? ''),
                'altNokRelat'  => sanitize($_POST['altNokRelat'] ?? ''),
                'email'        => sanitize($_POST['email'] ?? ''),
                'tel'          => sanitize($_POST['tel'] ?? ''),
                'unitAtt'      => sanitize($_POST['unitAtt'] ?? ''),
                'username'     => sanitize($_POST['username'] ?? ''),
                'password'     => isset($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null,
                'role'         => sanitize($_POST['role'] ?? ''),
                'renewDate'    => sanitize($_POST['renewDate'] ?? ''),
                'accStatus'    => sanitize($_POST['accStatus'] ?? ''),
                'createdBy'    => getCurrentUser()['id'] ?? null,
                'dateCreated'  => date('Y-m-d H:i:s'),
            ];

            $sql = "INSERT INTO staff (
                svcNo, rankID, lname, fname, NRC, passport, passExp, combatSize, bsize, ssize, hdress, gender, unitID, category, svcStatus, appt, subRank, subWef, tempRank, tempWef, localRank, localWef, attestDate, intake, DOB, height, province, corps, bloodGp, profession, trade, digitalID, prefix, marital, initials, titles, nok, nokNrc, nokRelat, nokTel, altNok, altNokTel, altNokNRC, altNokRelat, email, tel, unitAtt, username, password, role, renewDate, accStatus, createdBy, dateCreated
            ) VALUES (
                :svcNo, :rankID, :lname, :fname, :NRC, :passport, :passExp, :combatSize, :bsize, :ssize, :hdress, :gender, :unitID, :category, :svcStatus, :appt, :subRank, :subWef, :tempRank, :tempWef, :localRank, :localWef, :attestDate, :intake, :DOB, :height, :province, :corps, :bloodGp, :profession, :trade, :digitalID, :prefix, :marital, :initials, :titles, :nok, :nokNrc, :nokRelat, :nokTel, :altNok, :altNokTel, :altNokNRC, :altNokRelat, :email, :tel, :unitAtt, :username, :password, :role, :renewDate, :accStatus, :createdBy, :dateCreated
            )";

            try {
                $pdo = new PDO('mysql:host=localhost;dbname=armis;charset=utf8mb4', 'root', '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                $stmt = $pdo->prepare($sql);

                if ($stmt->execute($data)) {
                    // Insert spouse if married and spouse fields are present
                    if (
                        (isset($_POST['marital']) && strtolower($_POST['marital']) === 'married') &&
                        (!empty($_POST['spouse_name']) || !empty($_POST['spouse_dob']) || !empty($_POST['spouse_nrc']) || !empty($_POST['spouse_occupation']) || !empty($_POST['spouse_contact']))
                    ) {
                        $spouse_sql = "INSERT INTO staff_spouse (svcNo, spouseName, spouseDOB, spouseNRC, spouseOccup, spouseContact)
                                       VALUES (:svcNo, :spouseName, :spouseDOB, :spouseNRC, :spouseOccup, :spouseContact)";
                        $spouse_stmt = $pdo->prepare($spouse_sql);
                        $spouse_stmt->execute([
                            'svcNo'        => sanitize($_POST['svcNo']),
                            'spouseName'   => sanitize($_POST['spouse_name'] ?? ''),
                            'spouseDOB'    => sanitize($_POST['spouse_dob'] ?? ''),
                            'spouseNRC'    => sanitize($_POST['spouse_nrc'] ?? ''),
                            'spouseOccup'  => sanitize($_POST['spouse_occupation'] ?? ''),
                            'spouseContact'=> sanitize($_POST['spouse_contact'] ?? ''),
                        ]);
                    }

                    // Insert dependants if any
                    if (!empty($_POST['child_name'])) {
                        $child_names = sanitize_array($_POST['child_name']);
                        $child_dobs = sanitize_array($_POST['child_dob']);
                        $child_nrcs = sanitize_array($_POST['child_nrc']);
                        $child_relationships = sanitize_array($_POST['child_relationship']);
                        $child_genders = sanitize_array($_POST['child_gender']);
                        $dep_sql = "INSERT INTO staff_dependants (svcNo, name, dob, nrc, relationship, gender) VALUES (:svcNo, :name, :dob, :nrc, :relationship, :gender)";
                        $dep_stmt = $pdo->prepare($dep_sql);
                        for ($i = 0; $i < count($child_names); $i++) {
                            if ($child_names[$i] !== '') {
                                $dep_stmt->execute([
                                    'svcNo' => sanitize($_POST['svcNo']),
                                    'name' => $child_names[$i],
                                    'dob' => $child_dobs[$i] ?? null,
                                    'nrc' => $child_nrcs[$i] ?? null,
                                    'relationship' => $child_relationships[$i] ?? null,
                                    'gender' => $child_genders[$i] ?? null
                                ]);
                            }
                        }
                    }

                    // Insert appointments if any
                    if (!empty($_POST['appointment_name'])) {
                        $appt_names = sanitize_array($_POST['appointment_name']);
                        $appt_units = sanitize_array($_POST['appointment_unit']);
                        $appt_starts = sanitize_array($_POST['appointment_start']);
                        $appt_comments = sanitize_array($_POST['appointment_authority']);
                        $appt_sql = "INSERT INTO staff_appointment (apptID, svcNo, unitID, apptDate, comment, createdBy, dateCreated) VALUES (:apptID, :svcNo, :unitID, :apptDate, :comment, :createdBy, :dateCreated)";
                        $appt_stmt = $pdo->prepare($appt_sql);
                        for ($i = 0; $i < count($appt_names); $i++) {
                            if ($appt_names[$i] !== '') {
                                $appt_stmt->execute([
                                    'apptID'     => $appt_names[$i],
                                    'svcNo'      => sanitize($_POST['svcNo']),
                                    'unitID'     => $appt_units[$i] ?? null,
                                    'apptDate'   => $appt_starts[$i] ?? null,
                                    'comment'    => $appt_comments[$i] ?? null,
                                    'createdBy'  => getCurrentUser()['id'] ?? null,
                                    'dateCreated'=> date('Y-m-d H:i:s')
                                ]);
                            }
                        }
                    }

                    // --- INSERT STAFF CREATION NOTIFICATION FOR USER ---
                    // Use your preferred DB abstraction for this notification insert (PDO, DB class, etc.)
                    try {
                        $notif_sql = "INSERT INTO notifications (user_id, message, is_read, is_archived, date_created, class) 
                                      VALUES (:user_id, :message, 0, 0, :date_created, :class)";
                        $notif_stmt = $pdo->prepare($notif_sql);
                        $notif_stmt->execute([
                            'user_id' => getCurrentUser()['id'] ?? null,
                            'message' => 'Staff record for <b>'.sanitize($_POST['fname']).' '.sanitize($_POST['lname']).'</b> created successfully.',
                            'date_created' => date('Y-m-d H:i:s'),
                            'class' => 'success'
                        ]);
                    } catch (Exception $e) {
                        error_log('Notification insert error: ' . $e->getMessage());
                        // Do not block staff creation if notification insert fails
                    }

                    $success = true;
                    $_POST = [];
                    // Generate new CSRF token
                    CSRFToken::generate();
                    header("Location: create_staff.php?success=1");
                    exit;
                } else {
                    $errors[] = "Failed to save staff record.";
                }
            } catch (PDOException $e) {
                // Log error securely
                error_log('Database error: ' . $e->getMessage());
                $errors[] = "A database error occurred. Please contact the administrator.";
            }
        }
    }
}
if (isset($_GET['success'])) $success = true;