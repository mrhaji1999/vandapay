export default function UploadField({ label, hint, onChange, accept }) {
  return (
    <label className="upload-field">
      <span>{label}</span>
      <input type="file" accept={accept} onChange={onChange} hidden />
      <div className="upload-field-box">{hint || 'فایل را انتخاب کنید'}</div>
    </label>
  );
}
