export default function FormField({ label, error, children, description }) {
  return (
    <div className="form-field">
      <div className="form-field-header">
        <label>{label}</label>
        {description && <span className="form-field-description">{description}</span>}
      </div>
      {children}
      {error && <p className="form-field-error">{error}</p>}
    </div>
  );
}
