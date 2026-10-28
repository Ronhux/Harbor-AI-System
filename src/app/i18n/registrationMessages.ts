const fields: Record<string, string> = {first_name:'firstName', middle_name:'middleName', last_name:'lastName', suffix:'suffix', extension_name:'suffix', sex:'sex', barangay:'barangay', contact_number:'mobile', email:'email', password:'password', producer_type:'producerType', rsbsa_number:'rsbsa', terms_accepted:'terms', privacy_acknowledged:'privacy', terms_version:'terms', privacy_policy_version:'privacy', user_type:'userType'};
const messageKeys: Record<string, string> = {
"Enter a valid Philippine mobile number, like 09171234567 or +639171234567.": "register.validMobile",
"You must agree to the HarborAI Terms and Conditions to create an account.": "register.termsRequired",
"You must acknowledge that you have read the HarborAI Privacy Policy.": "register.privacyRequired",
"The HarborAI Terms and Conditions have been updated. Please review the current version and accept it again.": "register.termsUpdated",
"The HarborAI Privacy Policy has been updated. Please review the current version and acknowledge it again.": "register.privacyUpdated",
"The Terms and Conditions version is required. Please review and accept the current terms.": "register.termsVersionRequired",
"The Privacy Policy version is required. Please review and acknowledge the current policy.": "register.privacyVersionRequired",
  "Please complete the registry verification fields before continuing.": "register.completeIdentity",
  "Registry verification could not be completed. Please try again.": "register.verifyIncomplete",
  "Registry record verified.": "register.verifiedToast",
  "The information provided does not match the registry record associated with this RSBSA Number. Please review your details.": "register.mismatch",
  "Unable to verify this registry record right now. Please try again.": "register.verifyUnavailable",
  "Please verify the registry record before creating the producer account.": "register.verifyFirst",
  "Passwords do not match": "register.passwordMismatch",
  "Please agree to the Terms and Conditions and acknowledge the Privacy Policy.": "register.acceptBoth",
  "Please agree to the Terms and Conditions to continue.": "register.acceptTerms",
  "Please acknowledge that you have read the Privacy Policy to continue.": "register.acceptPrivacy",
  "Registration successful. Your account is active and ready to use.": "register.successToast",
  "The policies have been updated. Please reload this page, review them, and accept the current version.": "register.policyUpdated",
  "Registration failed. Please try again.": "register.failed",
  "Registration failed. Please check your connection and try again.": "register.connectionFailed",
  "Registry record not found. Please check your RSBSA Number and try again.": "register.notFound",
  "An account is already associated with this registry record.": "register.duplicateRegistry",
  "An account with this email address already exists.": "register.duplicateEmail",
  "An account already uses this mobile number. Use a different number, or sign in with your existing account.": "register.duplicateMobile",
  "Registration could not be completed. Please try again.": "register.incomplete"
};
export function translateRegistrationMessage(message: string, t: (key: string) => string): string {
  if (messageKeys[message]) return t(messageKeys[message]);
  const http = message.match(/^Registration could not be completed \(HTTP (\d+)\). Please try again or contact support\.$/);
  if (http) return t('register.httpError').replace('{status}', http[1]);
  const fieldLabel = (field: string) => {
    const key = fields[field.replace(/ /g, '_')];
    return key ? t('register.' + key) : field;
  };
  const patterns: [RegExp, string][] = [
    [/^The (.+?) field is required\.$/, 'validationRequired'],
    [/^The (.+?) field must be a valid email address\.$/, 'validationEmail'],
    [/^The (.+?) field must be at least (\d+) characters\.$/, 'validationMin'],
    [/^The (.+?) field must not be greater than (\d+) characters\.$/, 'validationMax'],
    [/^The (.+?) field must be a string\.$/, 'validationString'],
    [/^The selected (.+?) is invalid\.$/, 'validationSelected'],
    [/^The (.+?) field format is invalid\.$/, 'validationFormat'],
    [/^The (.+?) has already been taken\.$/, 'validationUnique'],
  ];
  for (const [pattern, key] of patterns) {
    const match = message.match(pattern);
    if (match) return t('register.' + key).replace('{field}', fieldLabel(match[1])).replace('{count}', match[2] ?? '');
  }
  const conditional = message.match(/^The (.+?) field is required when (.+?) is (.+?)\.$/);
  if (conditional) return t('register.validationConditional').replace('{field}', fieldLabel(conditional[1])).replace('{other}', fieldLabel(conditional[2])).replace('{value}', ['producer', 'buyer'].includes(conditional[3]) ? t('register.' + conditional[3]) : conditional[3]);
  const version = message.match(/^The (Terms and Conditions|Privacy Policy) version could not be verified against the active version \(expected (.+)\)\.$/);
  if (version) return t('register.versionMismatch').replace('{field}', t(version[1] === 'Terms and Conditions' ? 'register.terms' : 'register.privacy')).replace('{version}', version[2]);
  // Preserve unknown server details instead of hiding useful error information.
  return message.split('\n').map(line => {
    const separator = line.indexOf(': ');
    if (separator < 0) return line;
    const label = line.slice(0, separator).replace(/_/g, ' ').toLowerCase();
    const key = Object.keys(fields).find(field => field.replace(/_/g, ' ') === label);
    return (key ? t('register.' + fields[key]) : line.slice(0, separator)) + ': ' + translateRegistrationMessage(line.slice(separator + 2), t);
  }).join('\n');
}
