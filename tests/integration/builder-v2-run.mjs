// Real WordPress + SQLite, not the isolated WP/Woo doubles in tests/php.
// No HTTP listener, host filesystem mount, production database or outbound network.
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createHash } from "node:crypto";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import jquery from "jquery";
import { loadNodeRuntime } from "@php-wasm/node";
import { HttpCookieStore } from "@php-wasm/universal";
import { bootWordPressAndRequestHandler } from "@wp-playground/wordpress";

const [major, minor] = process.versions.node.split(".").map(Number);
assert.ok(
  major > 24 || (major === 24 && minor >= 18),
  "Integration SDK requires Node >=24.18 (the normal frontend build still supports Node 22.12+).",
);
const repo = fileURLToPath(new URL("../../", import.meta.url));
const themeSettingsAdminScript = await fs.readFile(path.join(repo, "assets/js/theme-settings-admin.js"), "utf8");
const visualBuilderScript = await fs.readFile(path.join(repo, "assets/js/visual-builder.js"), "utf8");
const archives = path.resolve(
  process.env.ZARRIN_INTEGRATION_DOWNLOADS ||
    path.join(repo, ".cache/integration/archives"),
);
const phpVersion = process.env.ZARRIN_TEST_PHP || "8.3";
const root = "/store/";
const encoder = new TextEncoder();
const results = [];
let jar = new HttpCookieStore();
const cookieStore = {
  rememberCookiesFromResponseHeaders: (headers) =>
    jar.rememberCookiesFromResponseHeaders(headers),
  getCookieRequestHeader: () => jar.getCookieRequestHeader(),
};
const freshVisitor = () => {
  jar = new HttpCookieStore();
};
function check(condition, label) {
  assert.ok(condition, label);
  results.push(label);
  console.log(`PASS: ${label}`);
}
async function archive(name, digest) {
  const bytes = await fs.readFile(path.join(archives, name));
  assert.equal(
    createHash("sha256").update(bytes).digest("hex"),
    digest,
    `${name}: untrusted fixture; run prepare:fixtures`,
  );
  return new File([bytes], name);
}
const handler = await bootWordPressAndRequestHandler({
  createPhpRuntime: () =>
    loadNodeRuntime(phpVersion, { emscriptenOptions: { processId: 1 } }),
  phpVersion,
  siteUrl: "https://shop.test/store/",
  cookieStore,
  maxPhpInstances: 1,
  wordPressZip: await archive(
    "wordpress-6.9.zip",
    "811bc36f11d587d8ae330b37e783f4b2d1e876c83f35409181e28613fc4dfb2a",
  ),
  sqliteIntegrationPluginZip: await archive(
    "sqlite-built-2.2.23.zip",
    "bef38f839cd7b74ed6dde1ecc26eae92ebe4eac4ab06f43357d31fc9f586c961",
  ),
  constants: {
    ZARRIN_INTEGRATION_TEST: true,
    WP_DEBUG: true,
    WP_DEBUG_DISPLAY: false,
    WP_DEBUG_LOG: true,
    WP_HTTP_BLOCK_EXTERNAL: true,
    DISABLE_WP_CRON: true,
    AUTOMATIC_UPDATER_DISABLED: true,
    WP_ENVIRONMENT_TYPE: "local",
  },
  hooks: {
    async beforeDatabaseSetup(php) {
      php.mkdirTree("/wordpress/wp-content/mu-plugins");
      php.writeFile(
        "/wordpress/wp-content/mu-plugins/zarrin-test-safety.php",
        await fs.readFile(new URL("safety.php", import.meta.url)),
      );
    },
  },
});

try {
  const php = await handler.getPrimaryPhp();
  const themePath = "/wordpress/wp-content/themes/zarrin";
  // Copy only the theme runtime into WASM memory. Never mount the checkout or its
  // .git, credentials, dependencies, fixtures, source archives or test scripts.
  async function copyRuntime(source, target) {
    php.mkdirTree(target);
    for (const entry of await fs.readdir(source, { withFileTypes: true })) {
      if (entry.name.startsWith(".")) continue;
      if (entry.isDirectory())
        await copyRuntime(
          path.join(source, entry.name),
          `${target}/${entry.name}`,
        );
      else if (entry.isFile())
        php.writeFile(
          `${target}/${entry.name}`,
          await fs.readFile(path.join(source, entry.name)),
        );
    }
  }
  php.mkdirTree(themePath);
  for (const entry of await fs.readdir(repo, { withFileTypes: true })) {
    if (
      entry.isFile() &&
      !(["11.php", "3.php"].includes(entry.name)) && (entry.name.endsWith(".php") || entry.name === "style.css")
    )
      php.writeFile(
        `${themePath}/${entry.name}`,
        await fs.readFile(path.join(repo, entry.name)),
      );
    if (
      entry.isDirectory() &&
      ["inc", "assets", "woocommerce", "template-parts"].includes(entry.name)
    )
      await copyRuntime(
        path.join(repo, entry.name),
        `${themePath}/${entry.name}`,
      );
  }
  async function phpJson(code) {
    const response = await php.run({
      code: `<?php require '/wordpress/wp-load.php'; $result = (function() { ${code}\n})(); echo '__ZARRIN_JSON__'.wp_json_encode($result);`,
    });
    // PHP-WASM can report exit=0 after a fatal: insist on the completion marker.
    assert.ok(
      response.text.startsWith("__ZARRIN_JSON__"),
      `PHP fixture failed: ${response.text.slice(0, 1000)} ${response.errors}`,
    );
    assert.equal(response.errors, "", "Unexpected PHP stderr");
    return JSON.parse(response.text.slice("__ZARRIN_JSON__".length));
  }
  async function request(
    url,
    { method = "POST", body, nonce, form = false } = {},
  ) {
    const headers = {};
    if (body !== undefined)
      headers["Content-Type"] = form
        ? "application/x-www-form-urlencoded"
        : "application/json";
    if (nonce) headers["X-WP-Nonce"] = nonce;
    const response = await handler.request({
      url,
      method,
      headers,
      ...(body === undefined
        ? {}
        : {
            body: encoder.encode(
              form
                ? new URLSearchParams(body).toString()
                : JSON.stringify(body),
            ),
          }),
    });
    return {
      status: response.httpStatusCode,
      text: response.text,
      headers: response.headers,
      json: () => JSON.parse(response.text),
    };
  }
  const rest = (route, body, options = {}) =>
    request(`${root}?rest_route=${encodeURIComponent(route)}`, {
      body,
      ...options,
    });
  const ajax = (body, options = {}) =>
    request(`${root}wp-admin/admin-ajax.php`, { body, form: true, ...options });
  const noStore = (response) => {
    const cache = Object.entries(response.headers).find(
      ([key]) => key.toLowerCase() === "cache-control",
    )?.[1];
    return /no-store/i.test(String(cache)) && /private/i.test(String(cache));
  };
  const getSession = async () => {
    const response = await ajax({ action: "jluxe_session" });
    assert.equal(response.status, 200);
    assert.ok(noStore(response));
    return response.json().data;
  };
  const login = async (user) => {
    const response = await rest("/jluxe/v1/auth/login", {
      login: user,
      password: "integration-only-password-2026",
    });
    assert.equal(response.status, 200, response.text);
    assert.equal(response.json().success, true);
    check(
      noStore(response),
      "Authentication response must be private/no-store",
    );
    return getSession();
  };

  const versions = await phpJson(
    `switch_theme('zarrin'); return array('wp'=>get_bloginfo('version'),'php'=>PHP_VERSION,'theme'=>get_stylesheet());`,
  );
  check(
    versions.wp === "6.9" && versions.theme === "zarrin",
    `Real WordPress ${versions.wp} + PHP ${versions.php} activates the theme`,
  );
  const users = await phpJson(`
    $users = array();
    foreach (array('customer'=>'subscriber','other'=>'subscriber','staff'=>'administrator','billing'=>'subscriber') as $name=>$role) {
      $id=wp_insert_user(array('user_login'=>'integration-'.$name,'user_pass'=>'integration-only-password-2026','user_email'=>$name.'@example.invalid','display_name'=>'Private fixture '.$name,'role'=>$role));
      if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
      $users[$name]=$id;
    }
    update_user_meta($users['customer'],'jluxe_phone','9120000000');
    update_user_meta($users['staff'],'jluxe_phone','9120000001');
    update_user_meta($users['billing'],'billing_phone','09120000002');
    update_option('users_can_register',0);
    update_option('timezone_string','Asia/Tehran');
    $settings=jluxe_theme_settings_defaults();
    $settings['sms']['enabled']=true;
    $settings['sms']['provider']='kavenegar';
    update_option(JLUXE_SETTINGS_OPTION,$settings);
    update_option(JLUXE_SMS_API_KEY_OPTION,'integration-only-no-real-provider');
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure('');
    flush_rewrite_rules(false);
    $users['trackingPage']=wp_insert_post(array('post_type'=>'page','post_name'=>'track-order','post_title'=>'Integration tracking fixture','post_status'=>'publish'));
    return $users;
  `);
  const home = await request(root, { method: "GET" });
  check(
    home.status === 200 &&
      home.text.includes("/assets/compiled/") &&
      !home.text.includes("Private fixture"),
    "Theme renders anonymously without WooCommerce or cached customer identity",
  );

  await login("integration-staff");
  const admin = await request(`${root}wp-admin/admin.php?page=jluxe-visual-builder`, {method:"GET"});
  check(admin.status===200 && admin.text.includes('id="jluxe-builder2"'), 'Actual WordPress admin route renders new standalone workspace');
  const match=admin.text.match(/var jluxeBuilder2\s*=\s*(\{[^;]+\});/);
  assert.ok(match, admin.text.slice(-1200));
  const config=JSON.parse(match[1]);
  check(Object.keys(config.types).length===24 && config.types.container, 'All 24 real widget types are available in localized library');
  const document={sections:[{id:'integration-container',type:'container',enabled:true,columns:2,mobile_columns:1,gap:24,children:[{id:'integration-heading',type:'heading',enabled:true,tag:'h3',title:'Integration nested heading'}]}]};
  const call=async(operation, value=document, revision=config.revision, nonce=config.nonce)=>ajax({action:'jluxe_builder2', operation, nonce, revision, document:JSON.stringify(value)});
  const invalid=await call('save',document,config.revision,'invalid');
  check(invalid.status===403, 'Real WordPress nonce rejects unauthorized builder writes');
  const before=await phpJson("return array('live'=>get_option(JLUXE_SETTINGS_OPTION),'draft'=>get_option(JLUXE_VISUAL_BUILDER_DRAFT_OPTION));");
  const inspector=await call('inspect');
  check(inspector.status===200 && inspector.json().data.html.includes('homepage[sections][0][columns]'), 'Inspector endpoint renders actual PHP container controls');
  const inspectorFixtures = {};
  for (const type of Object.keys(config.types)) {
    const result=await call('inspect',{sections:[{id:'test-'+type,type,enabled:true,children:[]}]});
    check(result.status===200 && result.json().success && result.json().data.html.includes('homepage[sections][0]'), 'Real WordPress inspector renders '+type);
    inspectorFixtures[type] = result.json().data.html;
  }
  const preview=await call('preview');
  check(preview.status===200 && preview.json().success, 'Actual AJAX creates private preview snapshot');
  const previewUrl=preview.json().data.url;
  const rendered=await request(previewUrl,{method:'GET'});
  check(rendered.status===200 && rendered.text.includes('Integration nested heading') && rendered.text.includes('data-jvb-node="integration-heading"') && rendered.text.includes('builder-preview.js'), 'Signed frontend preview renders nested content and authenticated selection bridge');
  if (process.env.BUILDER_CAPTURE) { await fs.mkdir(path.join(repo,'.cache/builder-capture'),{recursive:true}); await fs.writeFile(path.join(repo,'.cache/builder-capture/fixture.json'),JSON.stringify({admin:admin.text,config:{...config,nonce:'fixture-only'},inspectors:inspectorFixtures,preview:rendered.text})); }
  const after=await phpJson("return array('live'=>get_option(JLUXE_SETTINGS_OPTION),'draft'=>get_option(JLUXE_VISUAL_BUILDER_DRAFT_OPTION));");
  check(JSON.stringify(before)===JSON.stringify(after), 'Inspect and preview do not change active homepage or saved draft in real WordPress');
  const saved=await call('save');
  check(saved.status===200 && saved.json().success, 'Real WordPress saves full document draft');
  const afterDraft=await phpJson("return array('live'=>get_option(JLUXE_SETTINGS_OPTION),'draft'=>get_option(JLUXE_VISUAL_BUILDER_DRAFT_OPTION));");
  check(JSON.stringify(before.live)===JSON.stringify(afterDraft.live) && afterDraft.draft.homepage.sections[0].children[0].title==='Integration nested heading', 'Draft remains isolated from live homepage');
  const stale=await call('publish'); check(stale.status===409,'Stale editor cannot publish over a newer draft');
  const published=await call('publish',document,saved.json().data.revision);
  check(published.status===200 && published.json().success,'Explicit publish persists nested layout');

  // Drive the new desktop workspace against real WordPress AJAX, not mocked endpoint HTML.
  const editorDom=new JSDOM(admin.text,{url:'https://shop.test/store/wp-admin/admin.php?page=jluxe-visual-builder',runScripts:'outside-only',pretendToBeVisual:true});
  const ew=editorDom.window; ew.confirm=()=>true; ew.jQuery=jquery(ew);
  ew.jQuery.fn.sortable=function(){return this;}; ew.jQuery.fn.wpColorPicker=function(){return this;};
  ew.jluxeBuilder2={...config,homepage:{sections:[]},revision:published.json().data.revision};
  const editorCalls=[];
  ew.fetch=async (_url,init)=>{
    const fields=Object.fromEntries(init.body.entries());
    const response=await ajax(fields); editorCalls.push({operation:fields.operation,body:fields,response});
    return {ok:response.status>=200&&response.status<300,status:response.status,json:async()=>response.json()};
  };
  ew.eval(themeSettingsAdminScript);
  ew.eval(await fs.readFile(path.join(repo,'assets/js/builder-tree.js'),'utf8'));
  ew.eval(await fs.readFile(path.join(repo,'assets/js/visual-builder-v2.js'),'utf8'));
  async function until(predicate,label){for(let i=0;i<120;i++){if(predicate())return;await new Promise(r=>setTimeout(r,25));}throw new Error(label);}
  const ed=ew.document;
  check(ed.querySelectorAll('[data-jb-add]').length===24,'Actual admin UI exposes all real widget types as actionable library buttons');
  ed.querySelector('[data-jb-add="container"]').click();
  await until(()=>ed.querySelector('[name="homepage[sections][0][columns]"]'),'Container inspector failed');
  const desktopColumns=ed.querySelector('[name="homepage[sections][0][columns]"]'); desktopColumns.value='3'; desktopColumns.dispatchEvent(new ew.Event('input',{bubbles:true}));
  ed.querySelector('[data-jb-add="heading"]').click();
  await until(()=>ed.querySelector('[name="homepage[sections][0][tag]"]'),'Heading inspector failed');
  const headingField=ed.querySelector('[name="homepage[sections][0][title]"]'); headingField.value='Edited through actual UI'; headingField.dispatchEvent(new ew.Event('input',{bubbles:true}));
  ed.querySelector('[data-jb-action="save"]').click();
  await until(()=>editorCalls.some(x=>x.operation==='save'),'UI save failed');
  const uiSave=editorCalls.findLast(x=>x.operation==='save'); assert.equal(uiSave.response.status,200,uiSave.response.text);
  const uiSaved=uiSave.response.json().data.homepage;
  check(uiSaved.sections[0].columns===3&&uiSaved.sections[0].children[0].title==='Edited through actual UI','Actual desktop controls edit nested data and persist through real WordPress sanitizer');
  await until(()=>ed.querySelector('[data-jb-action="save"]').disabled===false,'Save never completed');
  const containerId=uiSaved.sections[0].id;
  ed.querySelector('[data-jb-select="'+containerId+'"]').click();
  await until(()=>ed.querySelector('[name="homepage[sections][0][columns]"]'),'Container reselect failed');
  ed.querySelector('[data-jb-add="hero"]').click();
  await until(()=>ed.querySelector('.jluxe-repeater-add'),'Hero inspector failed');
  ed.querySelector('.jluxe-repeater-add').click();
  await until(()=>ed.querySelector('[name="homepage[sections][0][items][0][image_id]"]'),'Native repeater add failed');
  const imageField=ed.querySelector('[name="homepage[sections][0][items][0][image_id]"]'); imageField.value='42';
  ew.jQuery(imageField).trigger('change');
  ed.querySelector('[data-jb-action="save"]').click();
  await until(()=>editorCalls.filter(x=>x.operation==='save').length===2,'Hero UI save failed');
  const heroSaved=editorCalls.findLast(x=>x.operation==='save').response.json();
  check(heroSaved.success&&heroSaved.data.homepage.sections[0].children[1].items[0].image_id===42,'Native WordPress repeater controls and jQuery media change events persist inside nested hero');
  editorDom.window.close();
  freshVisitor();
  const live=await request(root,{method:'GET'});
  check(live.status===200 && live.text.includes('Integration nested heading') && !live.text.includes('data-jvb-node=') && !live.text.includes('builder-preview.js'), 'Visitor receives published semantic layout without editor markers or bridge');
  const anonymous=await call('save'); check(anonymous.status===400 || anonymous.status===403,'Anonymous requests cannot use private editor endpoint');
  const privatePreview=await request(previewUrl,{method:'GET'});
  check(!privatePreview.text.includes('data-jvb-node='),'Signed preview still requires authenticated manager session');
  const debug=php.fileExists('/wordpress/wp-content/debug.log')?php.readFileAsText('/wordpress/wp-content/debug.log'):'';
  check(!/PHP (?:Fatal error|Parse error|Warning|Notice|Deprecated)|WordPress database error|Uncaught /i.test(debug),'Real WordPress log remains clean: '+debug.slice(-800));
  console.log(`BUILDER_V2_WORDPRESS_INTEGRATION_PASSED: ${results.length}`);
} finally {
  await handler[Symbol.asyncDispose]();
}
